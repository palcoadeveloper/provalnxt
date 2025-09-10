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
            $value = safe_get($field, 'string', '');
            
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
            $value = safe_get($field, 'string', '');
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
            
            // Applying a transition
            try {
                $stateMachine->apply($action);
            } catch (Exception $sm_error) {
                throw new Exception("State machine transition failed: " . $sm_error->getMessage());
            }
            
            // Insert audit trail
            DB::insert('audit_trail', [
                'val_wf_id' => $validated_data['val_wf_id'],
                'test_wf_id' => $validated_data['test_val_wf_id'],
                'user_id' => $_SESSION['user_id'],
                'user_type' => $_SESSION['logged_in_user'],
                'time_stamp' => DB::sqleval("NOW()"),
                'wf_stage' => $document->getFiniteState()
            ]);
            
            $unit_id = (isset($_SESSION['unit_id']) && $_SESSION['unit_id'] === "") ? 0 : $_SESSION['unit_id'];
            
            // Generate change description based on user type
            $change_description = '';
            if ($_SESSION['logged_in_user'] === 'vendor') {
                $change_description = 'External test submitted by ' . $_SESSION['user_domain_id'] . 
                                    '. Test WfID:' . $validated_data['test_val_wf_id'];
            } elseif ($_SESSION['logged_in_user'] === 'employee') {
                $change_description = 'External test reviewed by ' . $_SESSION['user_domain_id'] . 
                                    '. Test WfID:' . $validated_data['test_val_wf_id'];
            }
            
            DB::insert('log', [
                'change_type' => 'tran_ereview_approve',
                'table_name' => 'tbl_test_schedules_tracking',
                'change_description' => $change_description,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
            
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
                                
                                // Add QA approval stamp
                                $pdf->SetFont('Arial','B',10);
                                $pdf->SetXY(20, 133);
                                $pdf->Cell(50,30,'Certificate Approved By:',1,0,'C');
                                $pdf->SetFont('Arial','',10);
                                $pdf->MultiCell(0,10,$_SESSION['user_name']."\n".' Date: '.date("d.m.Y h:i:s A")."\n"."Quality Assurance (Cipla Ltd.)",1,'C');
                                
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
                
            } elseif ($action === "engg_approve") {
                // Handle Engineering approval with witness functionality
                $test_wf_id = $validated_data['test_val_wf_id'];
                $current_stage = $validated_data['current_wf_stage'] ?? null;
                
                // Check if conditions are met: data_entry_mode = 'online' and test_wf_current_stage = 2
                $test_conditions = DB::queryFirstRow("
                    SELECT tst.data_entry_mode, tst.test_wf_current_stage
                    FROM tbl_test_schedules_tracking tst
                    WHERE tst.test_wf_id = %s
                ", $test_wf_id);
                
                if ($test_conditions && 
                    $test_conditions['data_entry_mode'] === 'online' && 
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
                        
                        // Regenerate PDFs with witness details after approval
                        try {
                            // Include PDF regeneration function
                            require_once(__DIR__ . '/../save/regenerate_witness_pdfs.php');
                            
                            // Prepare witness details
                            $witnessDetails = [
                                'test_wf_id' => $test_wf_id,
                                'name' => $_SESSION['user_name'] ?? 'Unknown',
                                'employee_id' => $_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? 'N/A',
                                'department' => $_SESSION['department_name'] ?? 'Unknown Department',
                                'designation' => $_SESSION['designation'] ?? 'Unknown'
                            ];
                            
                            // Regenerate PDFs with witness details
                            $pdfRegenerationResult = regeneratePDFsWithWitness($test_wf_id, $witnessDetails);
                            
                            if ($pdfRegenerationResult) {
                                error_log("PDF regeneration with witness details completed successfully for test_wf_id: $test_wf_id");
                            } else {
                                error_log("PDF regeneration with witness details failed for test_wf_id: $test_wf_id");
                            }
                            
                        } catch (Exception $pdf_regen_error) {
                            error_log("Error during PDF regeneration with witness details for test_wf_id $test_wf_id: " . $pdf_regen_error->getMessage());
                        }
                    }
                }
                
            } elseif ($action === "engg_reject") {
                // Handle Engineering rejection with status inactivation
                $test_wf_id = $validated_data['test_val_wf_id'];
                
                // Check if conditions are met: data_entry_mode = 'online' and test_wf_current_stage = 2
                $test_conditions = DB::queryFirstRow("
                    SELECT tst.data_entry_mode, tst.test_wf_current_stage
                    FROM tbl_test_schedules_tracking tst
                    WHERE tst.test_wf_id = %s
                ", $test_wf_id);
                
                if ($test_conditions && 
                    $test_conditions['data_entry_mode'] === 'online' && 
                    $test_conditions['test_wf_current_stage'] == '2') {
                    
                    // Check if Engineering user (department_id = 1)
                    $user_dept = $_SESSION['department_id'] ?? null;
                    if ($user_dept == 1) {
                        
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
                        $test_specific_rows = DB::affectedRows(); // Get affected rows from the UPDATE operation
                        error_log("Engineering reject for test_wf_id: {$test_wf_id} by user: {$_SESSION['user_id']}");
                        error_log("- Set {$finalisation_rows} tbl_test_finalisation_details records to Inactive");
                        error_log("- Set {$test_specific_rows} Active test_specific_data records to Inactive (all versions preserved)");
                        
                    } else {
                        error_log("Engineering reject attempted by non-Engineering user. User department: {$user_dept}, test_wf_id: {$test_wf_id}");
                    }
                } else {
                    $mode = $test_conditions['data_entry_mode'] ?? 'unknown';
                    $stage = $test_conditions['test_wf_current_stage'] ?? 'unknown';
                    error_log("Engineering reject skipped - conditions not met. Mode: {$mode}, Stage: {$stage}, test_wf_id: {$test_wf_id}");
                }
            }
            
            // Update test workflow stage
            $test_conducted_date = isset($validated_data['test_conducted_date']) ? $validated_data['test_conducted_date'] : date('Y-m-d');
            
            DB::query("UPDATE tbl_test_schedules_tracking SET 
                      test_wf_current_stage = %s, 
                      test_conducted_date = %s, 
                      certi_submission_date = %?,
                      test_performed_by = %i 
                      WHERE test_wf_id = %s", 
                      $document->getFiniteState(), $test_conducted_date, DB::sqleval("NOW()"), 
                      $_SESSION['user_id'], $validated_data['test_val_wf_id']);
            
            return 'external_test_processed';
        }
    });
    
    // Redirect to assigned cases page
    redirect('assignedcases.php');
    
    // If using output buffering:
    ob_end_flush();
    
} catch (InvalidArgumentException $e) {
    error_log("Workflow stage validation error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Workflow stage update error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}

?>