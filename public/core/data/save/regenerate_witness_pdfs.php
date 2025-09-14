<?php
/**
 * ACPH PDF Regeneration with Witness Details
 * Regenerates ACPH Raw Data and Test Certificate PDFs with witness signature when documents are approved
 * 
 * Trigger conditions:
 * - test_wf_current_stage = 2 (document approval stage)
 * - paper_on_glass_enabled = 'Yes'
 * - data_entry_mode = 'online'
 * - Test type = ACPH
 * - Engineering approval action
 */

require_once(__DIR__ . '/../../config/config.php');
require_once(__DIR__ . '/../../config/db.class.php');
require_once(__DIR__ . '/../../pdf/acph_pdf_generator.php');

/**
 * Main function to regenerate ACPH PDFs with witness details
 * @param string $testWfId Test workflow ID
 * @param array $witnessDetails Witness/approver details
 * @param int $uploadId Upload ID to target specific record
 * @return bool Success status
 */
function regeneratePDFsWithWitness($testWfId, $witnessDetails, $uploadId) {
    try {
        error_log("Starting ACPH PDF regeneration with witness details for test_wf_id: $testWfId");
        
        // 1. Check if conditions are met for PDF regeneration
        if (!shouldRegeneratePDFs($testWfId)) {
            error_log("PDF regeneration conditions not met for test_wf_id: $testWfId");
            return false;
        }
        
        // 2. Get test conducted date from database
        $testInfo = DB::queryFirstRow("
            SELECT test_conducted_date 
            FROM tbl_test_schedules_tracking 
            WHERE test_wf_id = %s
        ", $testWfId);
        
        $testConductedDate = $testInfo['test_conducted_date'] ?? '';
        
        // 3. Prepare witness data for PDF generation
        // Get original finalization details from database
        $originalFinalization = DB::queryFirstRow("
            SELECT tfd.test_finalised_by, tfd.test_finalised_on, u.user_name as finalised_by_name
            FROM tbl_test_finalisation_details tfd
            LEFT JOIN users u ON tfd.test_finalised_by = u.user_id
            WHERE tfd.test_wf_id = %s AND tfd.status = 'Active'
        ", $testWfId);
        
        $witnessData = [
            'test_finalised_by' => $originalFinalization['test_finalised_by'] ?? null,
            'test_finalised_on' => $originalFinalization['test_finalised_on'] ?? null,
            'finalised_by_name' => $originalFinalization['finalised_by_name'] ?? 'Unknown',
            'witness' => $witnessDetails['employee_id'] ?? null,
            'witness_name' => $witnessDetails['name'] ?? 'Unknown Approver',
            'test_witnessed_on' => $witnessDetails['approval_timestamp'] ?? date('Y-m-d H:i:s')
        ];
        
        error_log("Witness data prepared: " . json_encode($witnessData));
        
        // 4. Check which document types exist in the original upload
        $existingDocTypes = getExistingDocumentTypes($testWfId);
        
        if (empty($existingDocTypes)) {
            error_log("ERROR: No existing document types found for test_wf_id: $testWfId");
            return false;
        }
        
        $regenerationResults = [];
        
        // 5. Selectively generate PDFs based on existing document types
        
        // Generate Raw Data PDF only if it existed originally
        if (in_array('raw_data', $existingDocTypes)) {
            error_log("Generating new ACPH Raw Data PDF with witness details (original exists)");
            $rawDataResult = generateACPHRawDataPDF($testWfId, $witnessData, $testConductedDate);
            
            if ($rawDataResult['success']) {
                // Update database with new Raw Data PDF path
                updateUploadRecord($testWfId, 'raw_data', $rawDataResult['upload_path'], $uploadId);
                $regenerationResults['raw_data'] = true;
                error_log("SUCCESS: ACPH Raw Data PDF regenerated: " . $rawDataResult['filename']);
            } else {
                error_log("ERROR: Failed to regenerate ACPH Raw Data PDF: " . ($rawDataResult['error'] ?? 'Unknown error'));
                $regenerationResults['raw_data'] = false;
            }
        } else {
            error_log("SKIPPING: Raw Data PDF regeneration (no original Raw Data PDF found)");
        }
        
        // Generate Test Certificate PDF only if it existed originally
        if (in_array('test_certificate', $existingDocTypes)) {
            error_log("Generating new ACPH Test Certificate PDF with witness details (original exists)");
            $certificateResult = generateACPHTestCertificatePDF($testWfId, $witnessData, $testConductedDate);
            
            if ($certificateResult['success']) {
                // Update database with new Test Certificate PDF path
                updateUploadRecord($testWfId, 'test_certificate', $certificateResult['upload_path'], $uploadId);
                $regenerationResults['test_certificate'] = true;
                error_log("SUCCESS: ACPH Test Certificate PDF regenerated: " . $certificateResult['filename']);
            } else {
                error_log("ERROR: Failed to regenerate ACPH Test Certificate PDF: " . ($certificateResult['error'] ?? 'Unknown error'));
                $regenerationResults['test_certificate'] = false;
            }
        } else {
            error_log("SKIPPING: Test Certificate PDF regeneration (no original Test Certificate PDF found)");
        }
        
        // Check if all attempted PDF regenerations were successful
        // We consider it successful if we either regenerated all intended documents or skipped appropriately
        $hasSuccess = !empty($regenerationResults) && !in_array(false, $regenerationResults, true);
        
        if (!$hasSuccess && !empty($regenerationResults)) {
            error_log("ERROR: One or more ACPH PDF regenerations failed for test_wf_id: $testWfId");
            error_log("Regeneration results: " . json_encode($regenerationResults));
            return false;
        }
        
        if (empty($regenerationResults)) {
            error_log("INFO: No ACPH PDFs needed regeneration for test_wf_id: $testWfId (no matching document types found)");
            return false;
        }
        
        // 6. Log successful regeneration
        logPDFRegeneration($testWfId, $witnessDetails, array_keys($regenerationResults));
        
        $regeneratedTypes = implode(', ', array_keys($regenerationResults));
        error_log("ACPH PDF regeneration completed successfully for test_wf_id: $testWfId. Regenerated types: $regeneratedTypes");
        return true;
        
    } catch (Exception $e) {
        error_log("Error in ACPH PDF regeneration for test_wf_id $testWfId: " . $e->getMessage());
        return false;
    }
}

/**
 * Get existing document types from upload record
 * @param string $testWfId Test workflow ID
 * @return array Array of document types that exist in the upload record
 */
function getExistingDocumentTypes($testWfId) {
    try {
        $uploadRecord = DB::queryFirstRow("
            SELECT upload_path_raw_data, upload_path_test_certificate, upload_path_master_certificate, upload_path_other_doc, upload_type
            FROM tbl_uploads 
            WHERE test_wf_id = %s 
            AND (upload_type = 'acph_test_documents' OR upload_type = 'raw_data' OR upload_type = 'test_certificate')
            ORDER BY uploaded_datetime DESC
            LIMIT 1
        ", $testWfId);
        
        if (!$uploadRecord) {
            error_log("No upload record found for test_wf_id: $testWfId");
            return [];
        }
        
        $existingTypes = [];
        
        // Check which document types exist (have non-empty paths and files exist)
        if (!empty($uploadRecord['upload_path_raw_data']) && 
            (strpos($uploadRecord['upload_path_raw_data'], 'uploads/') !== false || file_exists($uploadRecord['upload_path_raw_data']))) {
            $existingTypes[] = 'raw_data';
        }
        
        if (!empty($uploadRecord['upload_path_test_certificate']) && 
            (strpos($uploadRecord['upload_path_test_certificate'], 'uploads/') !== false || file_exists($uploadRecord['upload_path_test_certificate']))) {
            $existingTypes[] = 'test_certificate';
        }
        
        if (!empty($uploadRecord['upload_path_master_certificate']) && 
            (strpos($uploadRecord['upload_path_master_certificate'], 'uploads/') !== false || file_exists($uploadRecord['upload_path_master_certificate']))) {
            $existingTypes[] = 'master_certificate';
        }
        
        if (!empty($uploadRecord['upload_path_other_doc']) && 
            (strpos($uploadRecord['upload_path_other_doc'], 'uploads/') !== false || file_exists($uploadRecord['upload_path_other_doc']))) {
            $existingTypes[] = 'other_doc';
        }
        
        error_log("Existing document types for test_wf_id $testWfId: " . implode(', ', $existingTypes));
        return $existingTypes;
        
    } catch (Exception $e) {
        error_log("Error getting existing document types: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if PDF regeneration conditions are met
 * @param int $testWfId Test workflow ID
 * @return bool
 */
function shouldRegeneratePDFs($testWfId) {
    try {
        error_log("DEBUG: Checking conditions for test_wf_id: $testWfId (unit constraint removed)");
        
        $conditions = DB::queryFirstRow("
            SELECT 
                ts.test_wf_current_stage,
                t.paper_on_glass_enabled,
                ts.data_entry_mode
            FROM tbl_test_schedules_tracking ts
            LEFT JOIN tests t ON t.test_id = ts.test_id
            WHERE ts.test_wf_id = %s
        ", $testWfId);
        
        if (!$conditions) {
            error_log("ERROR: No test record found for test_wf_id: $testWfId");
            return false;
        }
        
        error_log("DEBUG: Test conditions - Stage: " . $conditions['test_wf_current_stage'] . 
                  ", PaperOnGlass: " . ($conditions['paper_on_glass_enabled'] ?? 'NULL') . 
                  ", DataEntryMode: " . ($conditions['data_entry_mode'] ?? 'NULL'));
        
        $result = ($conditions['test_wf_current_stage'] == 2 && 
                   ($conditions['paper_on_glass_enabled'] ?? 'No') == 'Yes' && 
                   ($conditions['data_entry_mode'] ?? '') == 'online');
        
        error_log("DEBUG: Conditions check result: " . ($result ? 'PASS' : 'FAIL'));
        return $result;
                
    } catch (Exception $e) {
        error_log("Error checking PDF regeneration conditions: " . $e->getMessage());
        return false;
    }
}

/**
 * Update upload record with new PDF file path
 * @param string $testWfId Test workflow ID
 * @param string $type Type of PDF ('raw_data' or 'test_certificate')
 * @param string $uploadPath New upload path
 * @param int $uploadId Upload ID to target specific record
 * @return bool Success status
 */
function updateUploadRecord($testWfId, $type, $uploadPath, $uploadId) {
    try {
        $columnMap = [
            'raw_data' => 'upload_path_raw_data',
            'test_certificate' => 'upload_path_test_certificate'
        ];
        
        if (!isset($columnMap[$type])) {
            error_log("ERROR: Invalid PDF type: $type");
            return false;
        }
        
        $column = $columnMap[$type];
        
        // Try to update existing record first
        $updated = DB::update('tbl_uploads', 
            [$column => $uploadPath], 
            'upload_id=%i', 
            $uploadId
        );
        
        if ($updated > 0) {
            error_log("SUCCESS: Updated existing upload record for upload_id: $uploadId, type: $type");
            return true;
        }
        
        // If no existing record, create a new one
        $uploadData = [
            'test_wf_id' => $testWfId,
            $column => $uploadPath,
            'upload_type' => $type,
            'uploaded_by' => $_SESSION['user_id'] ?? 1,
            'uploaded_datetime' => date('Y-m-d H:i:s'),
            'upload_status' => 'Active'
        ];
        
        $uploadId = DB::insert('tbl_uploads', $uploadData);
        
        if ($uploadId) {
            error_log("SUCCESS: Created new upload record (ID: $uploadId) for test_wf_id: $testWfId, type: $type");
            return true;
        } else {
            error_log("ERROR: Failed to create upload record for test_wf_id: $testWfId, type: $type");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error updating upload record: " . $e->getMessage());
        return false;
    }
}

/**
 * Test function to manually trigger ACPH PDF regeneration for debugging
 * @param string $testWfId Test workflow ID to test with
 * @param int $uploadId Upload ID to test with
 * @return bool Success status
 */
function testPDFRegeneration($testWfId, $uploadId = 0) {
    error_log("=== MANUAL ACPH PDF REGENERATION TEST START ===");
    error_log("Test WF ID: $testWfId, Upload ID: $uploadId");
    
    // Mock witness details for testing
    $witnessDetails = [
        'test_wf_id' => $testWfId,
        'name' => 'Test User',
        'employee_id' => 'TEST123',
        'department' => 'Test Department',
        'designation' => 'Test Engineer',
        'approval_timestamp' => date('Y-m-d H:i:s')
    ];
    
    $result = regeneratePDFsWithWitness($testWfId, $witnessDetails, $uploadId);
    
    error_log("=== MANUAL ACPH PDF REGENERATION TEST END ===");
    error_log("Result: " . ($result ? 'SUCCESS' : 'FAILED'));
    
    return $result;
}

/**
 * Log ACPH PDF regeneration event
 * @param string $testWfId Test workflow ID
 * @param array $witnessDetails Witness details
 * @param array $regeneratedTypes Array of document types that were regenerated
 */
function logPDFRegeneration($testWfId, $witnessDetails, $regeneratedTypes = []) {
    try {
        $typesString = !empty($regeneratedTypes) ? implode(', ', $regeneratedTypes) : 'none';
        
        DB::insert('log', [
            'change_type' => 'acph_pdf_regeneration_witness',
            'table_name' => 'tbl_test_schedules_tracking',
            'change_description' => "ACPH PDFs regenerated with witness details for test_wf_id: $testWfId. Document types: $typesString. Witness: " . ($witnessDetails['name'] ?? 'N/A'),
            'change_by' => $_SESSION['user_id'] ?? 0,
            'unit_id' => $_SESSION['unit_id'] ?? null
        ]);
        
        error_log("ACPH PDF regeneration logged for test_wf_id: $testWfId (types: $typesString)");
        
    } catch (Exception $e) {
        error_log("Error logging ACPH PDF regeneration: " . $e->getMessage());
    }
}

?>
