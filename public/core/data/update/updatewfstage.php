<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Suppress deprecation warnings
error_reporting(E_ALL & ~E_DEPRECATED);
// Or use output buffering
ob_start();

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once('../../security/secure_transaction_wrapper.php');

date_default_timezone_set("Asia/Kolkata");
include_once("../../config/db.class.php");
// Include workflow state machine with error handling
try {
    include_once '../../workflow/wf_ext_test.php';
} catch (Exception $e) {
    error_log("Failed to load workflow state machine: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Workflow system temporarily unavailable. Please try again later.'
    ]);
    exit();
}

// Apply rate limiting for form submissions
if (!RateLimiter::checkRateLimit('form_submission')) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Too many form submissions.']);
    exit();
}

// Validate CSRF token for POST requests using simple approach (consistent with rest of application)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}

// FPDI includes for PDF processing
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

// Fix FPDF library paths - they are in core/fpdf, not in local fpdf directory
$fpdf_path = __DIR__."/../../fpdf/fpdf.php";
$fpdi_autoload_path = __DIR__."/../../fpdf/fpdi/autoload.php";

if (!file_exists($fpdf_path)) {
    error_log("FPDF library not found at: " . $fpdf_path);
    echo json_encode([
        'status' => 'error', 
        'message' => 'PDF processing library unavailable. Please contact system administrator.'
    ]);
    exit();
}

if (!file_exists($fpdi_autoload_path)) {
    error_log("FPDI autoload not found at: " . $fpdi_autoload_path);
    echo json_encode([
        'status' => 'error', 
        'message' => 'PDF processing components unavailable. Please contact system administrator.'
    ]);
    exit();
}

require_once($fpdf_path);
require_once($fpdi_autoload_path);

// Input validation helper
class WorkflowStageValidator {
    public static function validateWorkflowUpdateData() {
        $required_fields = ['test_val_wf_id', 'val_wf_id'];
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            // Check POST data first, then GET data for backward compatibility
            $value = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$field])) {
                $value = safe_post($field, 'string', '');
            } else {
                $value = safe_get($field, 'string', '');
            }
            
            if (empty($value)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            // XSS detection on workflow IDs
            if (XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'update_workflow_stage');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate optional fields
        $optional_fields = ['action', 'test_type', 'test_conducted_date'];
        foreach ($optional_fields as $field) {
            // Check POST data first, then GET data for backward compatibility
            $value = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$field])) {
                $value = safe_post($field, 'string', '');
            } else {
                $value = safe_get($field, 'string', '');
            }
            if (!empty($value)) {
                if (XSSPrevention::detectXSS($value)) {
                    XSSPrevention::logXSSAttempt($value, 'update_workflow_stage');
                    throw new InvalidArgumentException("Invalid input detected in $field");
                }
                $validated_data[$field] = $value;
            }
        }
        
        // Validate date format if provided
        if (isset($validated_data['test_conducted_date']) && !empty($validated_data['test_conducted_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $validated_data['test_conducted_date']);
            if (!$date || $date->format('Y-m-d') !== $validated_data['test_conducted_date']) {
                throw new InvalidArgumentException("Invalid test conducted date format");
            }
        }
        
        return $validated_data;
    }
}

/**
 * Helper function to check if approval details should be skipped
 * for paper on glass + online mode scenarios
 * @param string $test_wf_id Test workflow ID
 * @return bool True if approval details should be skipped
 */
function shouldSkipApprovalDetails($test_wf_id) {
    if (empty($test_wf_id)) {
        return false;
    }
    
    try {
        $testConditions = DB::queryFirstRow("
            SELECT t.paper_on_glass_enabled, ts.data_entry_mode
            FROM tbl_test_schedules_tracking ts
            LEFT JOIN tests t ON t.test_id = ts.test_id
            WHERE ts.test_wf_id = %s
        ", $test_wf_id);
        
        return ($testConditions && 
                $testConditions['paper_on_glass_enabled'] == 'Yes' && 
                $testConditions['data_entry_mode'] == 'online');
                
    } catch (Exception $e) {
        error_log("Error checking approval details conditions: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to validate required uploads for offline paper-on-glass tests
 * @param string $test_wf_id Test workflow ID
 * @param string $stage_description Description of current stage for error message
 * @return bool True if validation passes
 * @throws InvalidArgumentException If required uploads are missing
 */
function validateOfflineTestUploads($test_wf_id, $stage_description) {
    $required_uploads = DB::queryFirstRow("
        SELECT upload_path_raw_data, upload_path_test_certificate, upload_path_master_certificate
        FROM tbl_uploads
        WHERE test_wf_id = %s
        AND upload_action IS NULL
        AND upload_status = 'Active'
        ORDER BY uploaded_datetime DESC
        LIMIT 1
    ", $test_wf_id);

    if (!$required_uploads ||
        empty($required_uploads['upload_path_raw_data']) ||
        empty($required_uploads['upload_path_test_certificate']) ||
        empty($required_uploads['upload_path_master_certificate'])) {

        throw new InvalidArgumentException(
            "For offline mode tests {$stage_description}, Raw Data, Certificate and Master Certificate files must be uploaded before submission."
        );
    }

    error_log("Upload validation passed for {$stage_description}: test_wf_id {$test_wf_id}");
    return true;
}

/**
 * Function to reject all uploaded files for a given workflow ID
 * Similar to individual file rejection but applied to all files
 * @param string $test_wf_id The workflow ID
 */
function rejectAllFilesForWorkflow($test_wf_id) {
    // Get all uploaded files for this workflow that are not already rejected
    $uploaded_files = DB::query("SELECT upload_id FROM tbl_uploads WHERE test_wf_id = %s AND (upload_action IS NULL OR upload_action != 'Rejected')", $test_wf_id);
    
    if (!empty($uploaded_files)) {
        // Update all files to rejected status
        DB::query("UPDATE tbl_uploads SET upload_action = 'Rejected' WHERE test_wf_id = %s AND (upload_action IS NULL OR upload_action != 'Rejected')", $test_wf_id);
        
        // Log each file rejection for audit trail
        $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
        
        foreach ($uploaded_files as $file) {
            $description = 'File automatically rejected due to QA task rejection. Upload ID: ' . $file['upload_id'] . ' Test WF ID: ' . $test_wf_id;
            
            DB::insert('log', [
                'change_type' => 'tran_upload_files_rej',
                'table_name' => 'tbl_uploads',
                'change_description' => $description,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
        }
    }
}

try {
    // Validate input data
    $validated_data = WorkflowStageValidator::validateWorkflowUpdateData();
    
    // Get current workflow stage and test type
    $query = "SELECT test_wf_current_stage, test_type FROM tbl_test_schedules_tracking WHERE test_wf_id = %s";
    $results = DB::queryFirstRow($query, $validated_data['test_val_wf_id']);

    if (empty($results)) {
        throw new Exception("No workflow found for the specified test workflow ID");
    }
    
    // Execute secure transaction
    $result = executeSecureTransaction(function() use ($validated_data, $results) {
        if (isset($validated_data['test_type'])) { // Internal Test
            // Insert audit trail
            DB::insert('audit_trail', [
                'val_wf_id' => $validated_data['val_wf_id'],
                'test_wf_id' => $validated_data['test_val_wf_id'],
                'user_id' => $_SESSION['user_id'],
                'user_type' => $_SESSION['logged_in_user'],
                'time_stamp' => DB::sqleval("NOW()"),
                'wf_stage' => 5
            ]);
            
            $unit_id = (isset($_SESSION['unit_id']) && $_SESSION['unit_id'] === "") ? 0 : $_SESSION['unit_id'];
            
            // Log internal test review
            DB::insert('log', [
                'change_type' => 'tran_ireview_approve',
                'table_name' => 'tbl_test_schedules_tracking',
                'change_description' => 'Internal test reviewed. UserID:' . intval($_SESSION['user_id']) . 
                                      ' Test WfID:' . $validated_data['test_val_wf_id'],
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
            
            // Update test workflow stage
            $test_conducted_date = isset($validated_data['test_conducted_date']) ? $validated_data['test_conducted_date'] : date('Y-m-d');
            
            DB::query("UPDATE tbl_test_schedules_tracking SET 
                      test_wf_current_stage = %s, 
                      test_conducted_date = %s,
                      certi_submission_date = %?,
                      test_performed_by = %i 
                      WHERE val_wf_id = %s AND test_wf_id = %s", 
                      5, $test_conducted_date, DB::sqleval("NOW()"), $_SESSION['user_id'], 
                      $validated_data['val_wf_id'], $validated_data['test_val_wf_id']);
            
            return 'internal_test_reviewed';
            
        } else { // External Test
            $current_stage = $results['test_wf_current_stage'];
            
            // State machine processing
            try {
                $document = new Document();
                $document->setFiniteState($current_stage);
                $stateMachine = new Finite\StateMachine\StateMachine($document);
                $loader = getWorkflowLoader();
                $loader->load($stateMachine);
                $stateMachine->initialize();
            } catch (Exception $sm_init_error) {
                error_log("State machine initialization error: " . $sm_init_error->getMessage());
                throw new Exception("Workflow system initialization failed. Please contact support.");
            }
            
            // Validate action if provided
            $action = isset($validated_data['action']) ? $validated_data['action'] : 'default_action';
            
            // Enhanced validation for offline mode submissions
            $is_offline_mode = false;
            if ($action === 'assign') {
                // Check if this is an offline mode test that requires document validation
                $test_mode_data = DB::queryFirstRow("
                    SELECT ts.data_entry_mode, ts.test_wf_current_stage, t.paper_on_glass_enabled
                    FROM tbl_test_schedules_tracking ts
                    INNER JOIN tests t ON t.test_id = ts.test_id
                    WHERE ts.test_wf_id = %s
                ", $validated_data['test_val_wf_id']);

                if ($test_mode_data &&
                    $test_mode_data['data_entry_mode'] === 'offline' &&
                    $test_mode_data['paper_on_glass_enabled'] === 'Yes') {

                    $is_offline_mode = true;

                    // Validate uploads based on current stage
                    if ($test_mode_data['test_wf_current_stage'] === '1RRV') {
                        // Validation for tests transitioning FROM 1RRV stage
                        validateOfflineTestUploads($validated_data['test_val_wf_id'], 'at 1RRV stage');
                    } else {
                        // Validation for tests transitioning TO 1PRV stage (existing logic)
                        validateOfflineTestUploads($validated_data['test_val_wf_id'], 'during initial submission');
                    }

                    // For offline mode, manually set the state to 1PRV instead of using state machine
                    DB::query("UPDATE tbl_test_schedules_tracking 
                              SET test_wf_current_stage = '1PRV',
                                  test_conducted_date = %s,
                                  certi_submission_date = %?,
                                  test_performed_by = %i 
                              WHERE test_wf_id = %s", 
                              (isset($validated_data['test_conducted_date']) ? $validated_data['test_conducted_date'] : date('Y-m-d')),
                              DB::sqleval("NOW()"), 
                              $_SESSION['user_id'], 
                              $validated_data['test_val_wf_id']);
                    
                    // Set the document state to 1PRV for consistency
                    $document->setFiniteState('1PRV');
                    
                    error_log("Set offline mode test to stage 1PRV for test_wf_id: {$validated_data['test_val_wf_id']}");
                }
            }
            
            // Applying a transition (skip for offline mode as we manually set the state)
            if (!$is_offline_mode) {
                try {
                    $stateMachine->apply($action);
                } catch (Exception $sm_error) {
                    throw new Exception("State machine transition failed: " . $sm_error->getMessage());
                }
            }
            
            // Skip generic audit trail for offline paper-on-glass reject actions that have specialized audit trail
            $skip_generic_audit_trail = false;
            if (($action === 'qa_reject' || $action === 'engg_reject') && isset($test_conditions)) {
                // Check if this is an offline paper-on-glass test that will have specialized audit trail
                $is_offline_paper_on_glass = (
                    $test_conditions['paper_on_glass_enabled'] === 'Yes' &&
                    $test_conditions['data_entry_mode'] === 'offline'
                );
                if ($is_offline_paper_on_glass) {
                    $skip_generic_audit_trail = true;
                }
            }

            // Insert audit trail with correct workflow stage (skip for specialized offline paper-on-glass reject actions)
            if (!$skip_generic_audit_trail) {
                $audit_stage = $is_offline_mode ? '1PRV' : $document->getFiniteState();
                DB::insert('audit_trail', [
                    'val_wf_id' => $validated_data['val_wf_id'],
                    'test_wf_id' => $validated_data['test_val_wf_id'],
                    'user_id' => $_SESSION['user_id'],
                    'user_type' => $_SESSION['logged_in_user'],
                    'time_stamp' => DB::sqleval("NOW()"),
                    'wf_stage' => $audit_stage
                ]);
            }
            
            $unit_id = (isset($_SESSION['unit_id']) && $_SESSION['unit_id'] === "") ? 0 : $_SESSION['unit_id'];
            
            // Generate change description based on user type and mode
            $change_description = '';
            $mode_suffix = $is_offline_mode ? ' (Offline Mode - Stage 1PRV)' : '';
            
            if ($_SESSION['logged_in_user'] === 'vendor') {
                $change_description = 'External test submitted by ' . $_SESSION['user_domain_id'] . 
                                    '. Test WfID:' . $validated_data['test_val_wf_id'] . $mode_suffix;
            } elseif ($_SESSION['logged_in_user'] === 'employee') {
                $change_description = 'External test reviewed by ' . $_SESSION['user_domain_id'] . 
                                    '. Test WfID:' . $validated_data['test_val_wf_id'] . $mode_suffix;
            }
            
            // Skip generic logging for offline paper-on-glass reject actions that have specialized logging
            $skip_generic_logging = false;
            if (($action === 'qa_reject' || $action === 'engg_reject') && isset($test_conditions)) {
                // Check if this is an offline paper-on-glass test that will have specialized logging
                $is_offline_paper_on_glass = (
                    $test_conditions['paper_on_glass_enabled'] === 'Yes' &&
                    $test_conditions['data_entry_mode'] === 'offline'
                );
                if ($is_offline_paper_on_glass) {
                    $skip_generic_logging = true;
                }
            }

            if (!$skip_generic_logging) {
                DB::insert('log', [
                    'change_type' => 'tran_ereview_approve',
                    'table_name' => 'tbl_test_schedules_tracking',
                    'change_description' => $change_description,
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $unit_id
                ]);
            }
            
            // Handle specific actions
            if ($action === "qa_approve") {
                if ($results['test_type'] == 'R') {
                    // Update routine test workflow
                    DB::query("UPDATE tbl_routine_test_wf_tracking_details SET 
                              routine_test_wf_current_stage = '5', 
                              stage_assigned_datetime = %?,
                              actual_wf_end_datetime = %? 
                              WHERE routine_test_wf_id = %s",
                              DB::sqleval("NOW()"), DB::sqleval("NOW()"), $validated_data['val_wf_id']);
                    
                    // Auto-schedule subsequent routine tests
                    if (isset($validated_data['test_conducted_date']) && !empty($validated_data['test_conducted_date'])) {
                        require_once '../../workflow/routine_auto_scheduler.php';
                        
                        $unit_id = (isset($_SESSION['unit_id']) && $_SESSION['unit_id'] !== "") ? $_SESSION['unit_id'] : 0;
                        $auto_schedule_result = autoScheduleSubsequentRoutineTests(
                            $validated_data['val_wf_id'], 
                            $validated_data['test_conducted_date'], 
                            $_SESSION['user_id'], 
                            $unit_id
                        );
                        
                        if (!$auto_schedule_result) {
                            error_log("Auto-scheduling failed for routine test: " . $validated_data['val_wf_id']);
                        }
                    }
                }
                
                // Auto-schedule subsequent validations for validation tests
                if ($results['test_type'] == 'V' && isset($validated_data['test_conducted_date']) && 
                    !empty($validated_data['test_conducted_date'])) {
                    require_once '../../workflow/validation_auto_scheduler.php';
                    
                    $unit_id = (isset($_SESSION['unit_id']) && $_SESSION['unit_id'] !== "") ? $_SESSION['unit_id'] : 0;
                    
                    // Get test_id and test_sch_id from the current test being processed
                    $test_info = DB::queryFirstRow("
                        SELECT test_id, test_sch_id 
                        FROM tbl_test_schedules_tracking 
                        WHERE test_wf_id = %s
                    ", $validated_data['test_val_wf_id']);
                    
                    if ($test_info) {
                        $auto_schedule_result = autoScheduleSubsequentValidations(
                            $validated_data['val_wf_id'], 
                            $test_info['test_id'],
                            $test_info['test_sch_id'],
                            $validated_data['test_conducted_date'], 
                            $_SESSION['user_id'], 
                            $unit_id
                        );
                        
                        if (!$auto_schedule_result) {
                            error_log("Validation auto-scheduling failed for: " . $validated_data['val_wf_id']);
                        }
                    } else {
                        error_log("Could not retrieve test info for validation auto-scheduling: " . $validated_data['test_val_wf_id']);
                    }
                }
                
                // Process approved test certificates with PDF stamping
                $test_files = DB::query("SELECT upload_path_test_certificate FROM tbl_uploads 
                                        WHERE test_wf_id = %s AND upload_action = 'Approved'", 
                                        $validated_data['test_val_wf_id']);
                
                if (!empty($test_files)) {
                    foreach ($test_files as $row) {
                        if (!empty($row['upload_path_test_certificate'])) {
                            try {
                                // Validate file exists
                                if (!file_exists($row['upload_path_test_certificate'])) {
                                    error_log("Certificate file not found: " . $row['upload_path_test_certificate']);
                                    continue;
                                }
                                
                                $pdf = new Fpdi();
                                $pageCount = $pdf->setSourceFile($row['upload_path_test_certificate']);
                                
                                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                                    $tplIdx = $pdf->importPage($pageNo, PdfReader\PageBoundaries::MEDIA_BOX);
                                    $s = $pdf->getTemplatesize($tplIdx);
                                    
                                    $pdf->addPage($s['orientation'], $s);
                                    $pdf->useTemplate($tplIdx);
                                }
                                
                                // Check if we should skip Certificate Approved By section for paper on glass + online mode
                                $shouldSkipApprovedBy = shouldSkipApprovalDetails($validated_data['test_val_wf_id']);
                                
                                if (!$shouldSkipApprovedBy) {
                                    // Add QA approval stamp
                                    $pdf->SetFont('Arial','B',10);
                                    $pdf->SetXY(20, 133);
                                    $pdf->Cell(50,30,'Certificate Approved By:',1,0,'C');
                                    $pdf->SetFont('Arial','',10);
                                    $pdf->MultiCell(0,10,$_SESSION['user_name']."\n".' Date: '.date("d.m.Y h:i:s A")."\n"."Quality Assurance (Cipla Ltd.)",1,'C');
                                }
                                
                                // Create secure new file path
                                $path_parts = pathinfo($row['upload_path_test_certificate']);
                                $new_path = $path_parts['dirname'] . '/' . $path_parts['filename'] . 'A.pdf';
                                
                                // Ensure directory is writable
                                if (!is_writable(dirname($new_path))) {
                                    error_log("Cannot write to directory: " . dirname($new_path));
                                    continue;
                                }
                                
                                $pdf->Output($new_path,'F');
                                
                                // Update database with new path
                                DB::query("UPDATE tbl_uploads SET upload_path_test_certificate = %s 
                                          WHERE upload_path_test_certificate = %s AND test_wf_id = %s AND upload_action = 'Approved'",
                                          $new_path, $row['upload_path_test_certificate'], $validated_data['test_val_wf_id']);
                                          
                            } catch (Exception $pdf_error) {
                                error_log("PDF processing error for file " . $row['upload_path_test_certificate'] . ": " . $pdf_error->getMessage());
                                // Continue processing other files
                            }
                        }
                    }
                }
                
            } elseif ($action === "qa_reject") {
                // When QA rejects the task, automatically reject all uploaded files
                rejectAllFilesForWorkflow($validated_data['test_val_wf_id']);

                // Get test conditions for QA rejection special handling
                $test_wf_id = $validated_data['test_val_wf_id'];
                $test_conditions = DB::queryFirstRow("
                    SELECT tst.data_entry_mode, tst.test_wf_current_stage, t.paper_on_glass_enabled
                    FROM tbl_test_schedules_tracking tst
                    LEFT JOIN tests t ON t.test_id = tst.test_id
                    WHERE tst.test_wf_id = %s
                ", $test_wf_id);

                // Special handling for offline paper-on-glass tests
                if ($test_conditions) {
                    $is_offline_paper_on_glass_qa_rejection = (
                        $test_conditions['paper_on_glass_enabled'] === 'Yes' &&
                        $test_conditions['data_entry_mode'] === 'offline' &&
                        $test_conditions['test_wf_current_stage'] == '3A'
                    );

                    if ($is_offline_paper_on_glass_qa_rejection) {
                        // Special handling for offline paper-on-glass QA rejection: change stage to 4BPRV
                        DB::query("UPDATE tbl_test_schedules_tracking
                                  SET test_wf_current_stage = '4BPRV'
                                  WHERE test_wf_id = %s", $test_wf_id);

                        $unit_id = (!empty($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id'])) ? (int)$_SESSION['unit_id'] : null;

                        // Insert audit trail with wf_stage = '4BPRV'
                        DB::insert('audit_trail', [
                            'val_wf_id' => $validated_data['val_wf_id'],
                            'test_wf_id' => $validated_data['test_val_wf_id'],
                            'user_id' => $_SESSION['user_id'],
                            'user_type' => $_SESSION['logged_in_user'],
                            'time_stamp' => DB::sqleval("NOW()"),
                            'wf_stage' => '4BPRV'
                        ]);

                        // Insert specialized log entry for offline paper-on-glass QA rejection
                        DB::insert('log', [
                            'change_type' => 'tran_off_qa_reject_4bprv',
                            'table_name' => 'tbl_test_schedules_tracking',
                            'change_description' => 'Task rejected by QA and assigned back to the vendor checker. Test WF:' . $validated_data['test_val_wf_id'] . ', Val WF:' . $validated_data['val_wf_id'],
                            'change_by' => $_SESSION['user_id'],
                            'unit_id' => $unit_id
                        ]);

                        error_log("QA reject - offline paper-on-glass test moved to 4BPRV. test_wf_id: {$test_wf_id} by user: {$_SESSION['user_id']}");

                        // Skip the normal state machine processing since we handled the stage manually
                        $skip_stage_update = true;
                    }
                }

            } elseif ($action === "engg_approve") {
                // Handle Engineering approval with witness functionality
                $test_wf_id = $validated_data['test_val_wf_id'];
                $current_stage = $validated_data['current_wf_stage'] ?? null;
                
                // Check if conditions are met: test_wf_current_stage = 2 (supports both online and offline tests)
                $test_conditions = DB::queryFirstRow("
                    SELECT tst.data_entry_mode, tst.test_wf_current_stage
                    FROM tbl_test_schedules_tracking tst
                    WHERE tst.test_wf_id = %s
                ", $test_wf_id);
                
                if ($test_conditions && 
                    $test_conditions['test_wf_current_stage'] == '2') {
                    
                    // Check if Engineering/QA user (department_id = 1 for Engineering, 8 for QA)
                    $user_dept = $_SESSION['department_id'] ?? null;
                    if ($user_dept == 1 || $user_dept == 8) {
                        
                        // Check if witness record already exists
                        $existing_witness = DB::queryFirstRow("
                            SELECT witness, test_witnessed_on 
                            FROM tbl_test_finalisation_details 
                            WHERE test_wf_id = %s AND status = 'Active'
                        ", $test_wf_id);
                        
                        if ($existing_witness && empty($existing_witness['witness'])) {
                            // Update existing record with witness information
                            DB::query("
                                UPDATE tbl_test_finalisation_details 
                                SET witness = %i, 
                                    test_witnessed_on = NOW(), 
                                    witness_action = 'approve'
                                WHERE test_wf_id = %s AND status = 'Active'
                            ", $_SESSION['user_id'], $test_wf_id);
                            
                            error_log("Witness data updated for test_wf_id: {$test_wf_id} by user: {$_SESSION['user_id']}");
                            
                        } elseif (!$existing_witness) {
                            // Create new witness record (this handles case where finalization hasn't occurred yet)
                            $witness_data = [
                                'test_wf_id' => $test_wf_id,
                                'witness' => $_SESSION['user_id'],
                                'test_witnessed_on' => date('Y-m-d H:i:s'),
                                'witness_action' => 'approve',
                                'status' => 'Active'
                            ];
                            
                            DB::insert('tbl_test_finalisation_details', $witness_data);
                            error_log("New witness record created for test_wf_id: {$test_wf_id} by user: {$_SESSION['user_id']}");
                        }
                    }
                }
                
            } elseif ($action === "engg_reject") {
                // Handle Engineering rejection with status inactivation
                $test_wf_id = $validated_data['test_val_wf_id'];
                
                // Check if conditions are met: test_wf_current_stage = 2 (supports both online and offline tests)
                $test_conditions = DB::queryFirstRow("
                    SELECT tst.data_entry_mode, tst.test_wf_current_stage, t.paper_on_glass_enabled
                    FROM tbl_test_schedules_tracking tst
                    LEFT JOIN tests t ON t.test_id = tst.test_id
                    WHERE tst.test_wf_id = %s
                ", $test_wf_id);
                
                if ($test_conditions && 
                    $test_conditions['test_wf_current_stage'] == '2') {
                    
                    // Check if Engineering user (department_id = 1)
                    $user_dept = $_SESSION['department_id'] ?? null;
                    if ($user_dept == 1) {
                        
                        // Check for special case: paper-on-glass enabled + offline mode + stage 2 rejection → 3BPRV
                        $is_offline_paper_on_glass_rejection = (
                            $test_conditions['paper_on_glass_enabled'] === 'Yes' &&
                            $test_conditions['data_entry_mode'] === 'offline' &&
                            $test_conditions['test_wf_current_stage'] == '2'
                        );
                        
                        if ($is_offline_paper_on_glass_rejection) {
                            // Special handling for offline paper-on-glass rejection: change stage to 3BPRV
                            DB::query("UPDATE tbl_test_schedules_tracking 
                                      SET test_wf_current_stage = '3BPRV'
                                      WHERE test_wf_id = %s", $test_wf_id);
                            
                            $unit_id = (!empty($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id'])) ? (int)$_SESSION['unit_id'] : null;
                            
                            // Insert audit trail with wf_stage = '3BPRV'
                            DB::insert('audit_trail', [
                                'val_wf_id' => $validated_data['val_wf_id'],
                                'test_wf_id' => $validated_data['test_val_wf_id'],
                                'user_id' => $_SESSION['user_id'],
                                'user_type' => $_SESSION['logged_in_user'],
                                'time_stamp' => DB::sqleval("NOW()"),
                                'wf_stage' => '3BPRV'
                            ]);
                            
                            // Insert specialized log entry for offline paper-on-glass rejection
                            DB::insert('log', [
                                'change_type' => 'tran_off_engg_reject_3bprv',
                                'table_name' => 'tbl_test_schedules_tracking',
                                'change_description' => 'Task rejected by engineering and assigned back to the vendor checker. Test WF:' . $validated_data['test_val_wf_id'] . ', Val WF:' . $validated_data['val_wf_id'],
                                'change_by' => $_SESSION['user_id'],
                                'unit_id' => $unit_id
                            ]);
                            
                            error_log("Engineering reject - offline paper-on-glass test moved to 3BPRV. test_wf_id: {$test_wf_id} by user: {$_SESSION['user_id']}");
                            
                        } else {
                            // Standard engineering rejection: set records to Inactive (for online tests or non-paper-on-glass)
                            
                            // 1. Set tbl_test_finalisation_details records to Inactive
                            $finalisation_updated = DB::query("
                                UPDATE tbl_test_finalisation_details 
                                SET status = 'Inactive'
                                WHERE test_wf_id = %s AND status = 'Active'
                            ", $test_wf_id);
                            
                            // 2. Set test_specific_data records to Inactive  
                            $test_data_updated = DB::query("
                                UPDATE test_specific_data
                                SET status = 'Inactive'
                                WHERE test_val_wf_id = %s AND status = 'Active'
                            ", $test_wf_id);
                            
                            // Log the status changes
                            $finalisation_rows = DB::affectedRows();
                            $test_specific_rows = DB::affectedRows();
                            error_log("Engineering reject (standard) for test_wf_id: {$test_wf_id} by user: {$_SESSION['user_id']}");
                            error_log("- Set {$finalisation_rows} tbl_test_finalisation_details records to Inactive");
                            error_log("- Set {$test_specific_rows} Active test_specific_data records to Inactive (all versions preserved)");
                        }
                        
                    } else {
                        error_log("Engineering reject attempted by non-Engineering user. User department: {$user_dept}, test_wf_id: {$test_wf_id}");
                    }
                } else {
                    $stage = $test_conditions['test_wf_current_stage'] ?? 'unknown';
                    error_log("Engineering reject skipped - test not in stage 2. Current stage: {$stage}, test_wf_id: {$test_wf_id}");
                }
            }
            
            // Update test workflow stage (skip if already set to 3BPRV or 4BPRV for offline paper-on-glass rejections)
            $current_stage_check = DB::queryFirstRow("SELECT test_wf_current_stage FROM tbl_test_schedules_tracking WHERE test_wf_id = %s", $validated_data['test_val_wf_id']);
            $skip_stage_update = ($current_stage_check && ($current_stage_check['test_wf_current_stage'] === '3BPRV' || $current_stage_check['test_wf_current_stage'] === '4BPRV'));
            
            if (!$skip_stage_update) {
                $test_conducted_date = isset($validated_data['test_conducted_date']) ? $validated_data['test_conducted_date'] : date('Y-m-d');
                
                DB::query("UPDATE tbl_test_schedules_tracking SET 
                          test_wf_current_stage = %s, 
                          test_conducted_date = %s, 
                          certi_submission_date = %?,
                          test_performed_by = %i 
                          WHERE test_wf_id = %s", 
                          $document->getFiniteState(), $test_conducted_date, DB::sqleval("NOW()"), 
                          $_SESSION['user_id'], $validated_data['test_val_wf_id']);
            } else {
                // For 3BPRV and 4BPRV cases, only update other fields without changing the stage
                $test_conducted_date = isset($validated_data['test_conducted_date']) ? $validated_data['test_conducted_date'] : date('Y-m-d');

                DB::query("UPDATE tbl_test_schedules_tracking SET
                          test_conducted_date = %s,
                          certi_submission_date = %?,
                          test_performed_by = %i
                          WHERE test_wf_id = %s",
                          $test_conducted_date, DB::sqleval("NOW()"),
                          $_SESSION['user_id'], $validated_data['test_val_wf_id']);

                $preserved_stage = $current_stage_check['test_wf_current_stage'];
                error_log("Preserved {$preserved_stage} stage for test_wf_id: {$validated_data['test_val_wf_id']}");
            }
            
            return 'external_test_processed';
        }
    });
    
    // Check if this is an AJAX request
    $isAjaxRequest = (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        !empty($_SERVER['CONTENT_TYPE']) && 
        strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
    ) || (
        isset($_POST['csrf_token']) && // Modal-based requests typically include CSRF token
        $_SERVER['REQUEST_METHOD'] === 'POST'
    );
    
    if ($result) {
        if ($isAjaxRequest) {
            // Return JSON response for AJAX requests (modal-based)
            echo json_encode([
                'status' => 'success', 
                'message' => 'Test processed successfully',
                'csrf_token' => generateCSRFToken()
            ]);
        } else {
            // Redirect for non-AJAX requests
            redirect('assignedcases.php');
        }
    } else {
        if ($isAjaxRequest) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Failed to process request',
                'csrf_token' => generateCSRFToken()
            ]);
        } else {
            redirect('assignedcases.php?error=processing_failed');
        }
    }
    
    // If using output buffering and not redirecting:
    if ($isAjaxRequest) {
        ob_end_flush();
    }
    
} catch (InvalidArgumentException $e) {
    error_log("Workflow stage validation error: " . $e->getMessage());
    
    // Check if AJAX request for consistent error handling
    $isAjaxRequest = (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        isset($_POST['csrf_token']) && $_SERVER['REQUEST_METHOD'] === 'POST'
    );
    
    if ($isAjaxRequest) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'csrf_token' => generateCSRFToken()
        ]);
    } else {
        redirect('assignedcases.php?error=validation_error');
    }
} catch (Exception $e) {
    error_log("Workflow stage update error: " . $e->getMessage());
    
    // Check if AJAX request for consistent error handling
    $isAjaxRequest = (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        isset($_POST['csrf_token']) && $_SERVER['REQUEST_METHOD'] === 'POST'
    );
    
    if ($isAjaxRequest) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error occurred: ' . $e->getMessage(),
            'csrf_token' => generateCSRFToken()
        ]);
    } else {
        redirect('assignedcases.php?error=database_error');
    }
}

?>