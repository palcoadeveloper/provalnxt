<?php
// Start output buffering to prevent any unwanted output
ob_start();

require_once('../../config/config.php');
require_once('../../config/db.class.php');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');


// Simple session check (avoid complex middleware for now)
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

try {
    // Simple input validation
    if (empty($_POST['test_wf_id'])) {
        throw new Exception('test_wf_id is required');
    }
    
    $test_wf_id = trim($_POST['test_wf_id']);
    $test_conducted_date = trim($_POST['test_conducted_date'] ?? '');
    
    // Check condition 1: Data entry completeness for all filters
    $acph_data = DB::query("
        SELECT section_type, data_json 
        FROM test_specific_data 
        WHERE test_val_wf_id = %s AND status = 'Active'
    ", $test_wf_id);
    
    if (empty($acph_data)) {
        throw new Exception('No test data found for the given test workflow ID.');
    }
    
    // Process all data_json entries with their section types
    $testData = [];
    foreach ($acph_data as $row) {
        $json_data = json_decode($row['data_json'], true);
        if ($json_data && !empty($row['section_type'])) {
            $testData[$row['section_type']] = $json_data;
        }
    }
    if (empty($testData)) {
        throw new Exception('No test data found.');
    }
    
    // Get test info
    $test_info = DB::queryFirstRow("
        SELECT e.equipment_id, e.equipment_code, e.area_served
        FROM tbl_test_schedules_tracking t
        JOIN equipments e ON t.equip_id = e.equipment_id
        WHERE t.test_wf_id = %s
    ", $test_wf_id);
    
    if (!$test_info) {
        throw new Exception('Equipment information not found.');
    }
    
    // Get all filters for this equipment using filter_id
    $filters = DB::query("
        SELECT em.filter_id, em.filter_name, fg.filter_group_name
        FROM erf_mappings em
        JOIN filter_groups fg ON em.filter_group_id = fg.filter_group_id
        WHERE em.equipment_id = %i AND em.erf_mapping_status = 'Active'
    ", $test_info['equipment_id']);
    
    if (empty($filters)) {
        throw new Exception('No active filters found for this equipment.');
    }
    
    // Check data completeness for each filter using filter_id
    $incomplete_filters = [];
    foreach ($filters as $filter) {
        $filter_id = $filter['filter_id'];
        
        // Find the corresponding data in test_specific_data using filter_id
        $filter_data = null;
        foreach ($acph_data as $row) {
            $json_data = json_decode($row['data_json'], true);
            if ($json_data && isset($json_data['filter_id']) && $json_data['filter_id'] == $filter_id) {
                $filter_data = $json_data;
                break;
            }
        }
        
        if (!$filter_data) {
            $incomplete_filters[] = $filter['filter_name'];
            continue;
        }
        
        // Check if all 5 readings are present and not empty
        if (isset($filter_data['readings']) && is_array($filter_data['readings'])) {
            // New nested format
            for ($i = 1; $i <= 5; $i++) {
                $reading_key = "reading_$i";
                if (!isset($filter_data['readings'][$reading_key]['value']) || 
                    empty($filter_data['readings'][$reading_key]['value'])) {
                    $incomplete_filters[] = $filter['filter_name'] . " (Missing R$i)";
                }
            }
        } else {
            // Old flat format - check for reading_1 to reading_5
            for ($i = 1; $i <= 5; $i++) {
                $reading_key = "reading_$i";
                if (!isset($filter_data[$reading_key]) || empty($filter_data[$reading_key])) {
                    $incomplete_filters[] = $filter['filter_name'] . " (Missing R$i)";
                }
            }
        }
        
        // Check if flow_rate is present
        if (!isset($filter_data['flow_rate']) || empty($filter_data['flow_rate'])) {
            $incomplete_filters[] = $filter['filter_name'] . " (Missing Flow Rate)";
        }
    }
    
    if (!empty($incomplete_filters)) {
        throw new Exception('Data entry incomplete for filters: ' . implode(', ', $incomplete_filters));
    }
    
    // Check condition 2: No active record in tbl_test_finalisation_details
    $existing_active = DB::queryFirstRow("
        SELECT tfd.test_id, tfd.test_finalised_on, tfd.test_finalised_by, u.user_name
        FROM tbl_test_finalisation_details tfd
        LEFT JOIN users u ON tfd.test_finalised_by = u.user_id
        WHERE tfd.test_wf_id = %s AND tfd.status = 'Active'
    ", $test_wf_id);
    
    if ($existing_active) {
        $finalized_on = date('d/m/Y H:i', strtotime($existing_active['test_finalised_on']));
        $finalized_by = $existing_active['user_name'] ?? 'Unknown User';
        throw new Exception("Test data has already been finalized on {$finalized_on} by {$finalized_by}. Cannot finalize again.");
    }
    
    // Get current data entry mode to determine if PDF generation is needed
    $current_mode_data = DB::queryFirstRow("
        SELECT data_entry_mode 
        FROM tbl_test_schedules_tracking 
        WHERE test_wf_id = %s
    ", $test_wf_id);
    
    $data_entry_mode = $current_mode_data['data_entry_mode'] ?? 'online';
    $skip_pdf_generation = ($data_entry_mode === 'offline');
    
    // Both conditions met - proceed with finalization
    // Start transaction manually
    DB::query("START TRANSACTION");
    
    try {
        if (!$skip_pdf_generation) {
            // Only generate PDFs if not in offline mode
            // Use the new ACPH PDF generation functions
            require_once('../../pdf/acph_pdf_generator.php');
        
        // Prepare witness data for PDF generation
        $current_user_info = DB::queryFirstRow("
            SELECT user_name FROM users WHERE user_id = %i
        ", $_SESSION['user_id']);
        
        $witnessData = [
            'test_finalised_by' => $_SESSION['user_id'],
            'test_finalised_on' => date('Y-m-d H:i:s'),
            'witness' => null, // No witness during finalization
            'test_witnessed_on' => null,
            'finalised_by_name' => $current_user_info['user_name'] ?? 'Unknown User',
            'witness_name' => null
        ];
        
        // Get comprehensive test and workflow information - matching preview_raw_data.php
        $test_info = DB::queryFirstRow("
            SELECT 
                ts.test_wf_id,
                ts.val_wf_id,
                ts.equip_id,
                ts.test_id,
                ts.test_wf_planned_start_date,
                ts.test_conducted_date,
                t.test_name,
                t.test_purpose,
                t.test_description,
                e.equipment_code,
                e.area_served,
                e.section,
                u.unit_name
            FROM tbl_test_schedules_tracking ts
            JOIN tests t ON ts.test_id = t.test_id
            JOIN equipments e ON ts.equip_id = e.equipment_id
            LEFT JOIN units u ON ts.unit_id = u.unit_id
            WHERE ts.test_wf_id = %s
        ", $test_wf_id);
        
        // Get room location and area classification information from ERF mappings
        $room_info = DB::queryFirstRow("
            SELECT DISTINCT
                rl.room_loc_name,
                em.area_classification
            FROM erf_mappings em
            JOIN room_locations rl ON em.room_loc_id = rl.room_loc_id
            WHERE em.equipment_id = %i
            AND em.erf_mapping_status = 'Active'
            LIMIT 1
        ", $test_info['equip_id']);
        
        // Set room and area info, with fallbacks
        $room_name = $room_info['room_loc_name'] ?? 'N/A';
        $area_classification = $room_info['area_classification'] ?? 'N/A';
        
        // Get ACPH filter data (stored as individual filter records) - matching preview logic
        $filter_data_records = DB::query("
            SELECT tsd.data_json, tsd.section_type, tsd.entered_date, tsd.modified_date, tsd.filter_id,
                   u1.user_name as entered_by_name, u2.user_name as modified_by_name
            FROM test_specific_data tsd
            LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
            LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
            WHERE tsd.test_val_wf_id = %s 
            AND tsd.section_type LIKE 'acph_filter_%'
            AND tsd.status = 'Active'
            ORDER BY tsd.section_type
        ", $test_wf_id);
        
        // Get all instrument details for this test
        $instruments_info = DB::query("
            SELECT 
                i.instrument_type,
                i.instrument_id,
                i.serial_number,
                i.calibrated_on,
                i.calibration_due_on,
                CASE 
                    WHEN i.calibration_due_on < NOW() THEN 'Expired'
                    WHEN i.calibration_due_on < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Due Soon' 
                    ELSE 'Valid'
                END as calibration_status,
                i.instrument_status,
                ti.added_date,
                ti.added_by,
                u.user_name as added_by_name
            FROM test_instruments ti
            JOIN instruments i ON ti.instrument_id = i.instrument_id
            LEFT JOIN users u ON ti.added_by = u.user_id
            WHERE ti.test_val_wf_id = %s
            AND ti.is_active = '1'
            ORDER BY ti.added_date ASC
        ", $test_wf_id);
        
        // Create an instrument lookup array for easier access
        $instruments_lookup = [];
        foreach ($instruments_info as $instrument) {
            $instruments_lookup[$instrument['instrument_id']] = $instrument;
        }
        
        // Note: getInstrumentSummaryForFilter function is now available from acph_pdf_generator.php
        
        // Get filter group information
        $filter_groups = DB::query("
            SELECT DISTINCT
                fg.filter_group_name,
                em.filter_id
            FROM erf_mappings em
            JOIN filter_groups fg ON em.filter_group_id = fg.filter_group_id
            WHERE em.equipment_id = %i
            AND em.erf_mapping_status = 'Active'
            AND fg.status = 'Active'
        ", $test_info['equip_id']);
        
        // Since we're in the process of finalizing, create witness data from current session
        // The finalization record will be inserted later, but we need this for the PDF
        $current_user_info = DB::queryFirstRow("
            SELECT user_name FROM users WHERE user_id = %i
        ", $_SESSION['user_id']);
        
        $witness_data = [
            'test_finalised_by' => $_SESSION['user_id'],
            'test_finalised_on' => date('Y-m-d H:i:s'),
            'witness' => null, // Will be set if witness is provided
            'test_witnessed_on' => null,
            'finalised_by_name' => $current_user_info['user_name'] ?? 'Unknown User',
            'witness_name' => null
        ];
        
        // Debug logging for witness data
        error_log("Witness data query for test_wf_id {$test_wf_id}: " . json_encode($witness_data));
        
        // Process ACPH data from individual filter records - exact match with preview logic
        $filters = [];
        $room_volume = null;
        $grand_total_supply_cfm = 0;
        $calculated_acph = null;
        $total_flow_rate_sum = 0;
        
        foreach ($filter_data_records as $record) {
            $filter_data = json_decode($record['data_json'], true);
            if (!$filter_data) {
                continue;
            }
            
            // Extract filter ID from section_type or use database filter_id
            preg_match('/acph_filter_(\d+)/', $record['section_type'], $matches);
            $filter_id = $matches[1] ?? $record['filter_id'] ?? 'unknown';
            
            // Get filter group for this filter
            $filter_group_name = 'Ungrouped';
            foreach ($filter_groups as $group) {
                if ($group['filter_id'] == $filter_id) {
                    $filter_group_name = $group['filter_group_name'];
                    break;
                }
            }
            
            // Calculate average readings - use stored average if available, otherwise calculate
            $readings = [];
            $reading_instruments = []; // Store instrument info for each reading
            $reading_sum = 0;
            $reading_count = 0;
            
            // Check if readings are stored in the new nested format
            if (isset($filter_data['readings']) && is_array($filter_data['readings'])) {
                // New format: readings: { "reading_1": {"value": "5", "instrument_id": "INs234"}, ... }
                for ($i = 1; $i <= 5; $i++) {
                    $reading_key = "reading_$i";
                    if (isset($filter_data['readings'][$reading_key])) {
                        $reading_value = $filter_data['readings'][$reading_key]['value'] ?? '';
                        $instrument_id = $filter_data['readings'][$reading_key]['instrument_id'] ?? '';
                        
                        $readings[$reading_key] = $reading_value;
                        $reading_instruments[$reading_key] = $instrument_id;
                        
                        if (is_numeric($reading_value) && $reading_value > 0) {
                            $reading_sum += floatval($reading_value);
                            $reading_count++;
                        }
                    } else {
                        $readings[$reading_key] = '';
                        $reading_instruments[$reading_key] = '';
                    }
                }
            } else {
                // Old format: reading_1, reading_2, etc. as direct fields
                for ($i = 1; $i <= 5; $i++) {
                    $reading_value = $filter_data["reading_$i"] ?? '';
                    $readings["reading_$i"] = $reading_value;
                    $reading_instruments["reading_$i"] = ''; // No instrument info in old format
                    
                    if (is_numeric($reading_value) && $reading_value > 0) {
                        $reading_sum += floatval($reading_value);
                        $reading_count++;
                    }
                }
            }
            
            // Use stored average if available, otherwise calculate
            $average_reading = 0;
            if (isset($filter_data['average']) && is_numeric($filter_data['average'])) {
                $average_reading = floatval($filter_data['average']);
            } elseif ($reading_count > 0) {
                $average_reading = round($reading_sum / $reading_count, 2);
            }
            
            // Use stored flow rate value from database
            $cell_area = floatval($filter_data['cell_area'] ?? 0);
            $flow_rate = floatval($filter_data['flow_rate'] ?? 0);
            $total_flow_rate_sum += $flow_rate;
            
            // Add filter data
            $filters[$filter_id] = [
                'filter_code' => $filter_data['filter_code'] ?? "AHU-01/THF/0.3μm/0$filter_id/A",
                'filter_group' => $filter_group_name,
                'cell_area' => $cell_area,
                'flow_rate' => round($flow_rate, 2),
                'readings' => $readings,
                'reading_instruments' => $reading_instruments,
                'average_reading' => $average_reading,
                'total_flow_rate' => round($flow_rate, 2),
                'entered_by' => $record['entered_by_name'] ?? 'N/A',
                'entered_date' => $record['entered_date'] ?? null,
                'modified_by' => $record['modified_by_name'] ?? null,
                'modified_date' => $record['modified_date'] ?? null
            ];
            
            // Get global values from stored data or ERF mappings
            if ($room_volume === null) {
                $room_volume = $filter_data['room_volume'] ?? null;
            }
            if (isset($filter_data['grand_total_supply_cfm']) && is_numeric($filter_data['grand_total_supply_cfm'])) {
                $grand_total_supply_cfm = floatval($filter_data['grand_total_supply_cfm']);
            }
            if (isset($filter_data['calculated_acph']) && is_numeric($filter_data['calculated_acph'])) {
                $calculated_acph = floatval($filter_data['calculated_acph']);
            }
        }
        
        // Calculate grand total CFM if not stored (sum of all filter flow rates)
        if ($grand_total_supply_cfm == 0 && $total_flow_rate_sum > 0) {
            $grand_total_supply_cfm = round($total_flow_rate_sum, 2);
        }
        
        // Get room volume from equipment if not in filter data
        if (!$room_volume && $room_info) {
            // Try to get room volume from room_locations table
            $room_volume_query = DB::queryFirstRow("
                SELECT rl.room_volume 
                FROM room_locations rl 
                JOIN erf_mappings em ON rl.room_loc_id = em.room_loc_id 
                WHERE em.equipment_id = %i 
                AND em.erf_mapping_status = 'Active' 
                LIMIT 1
            ", $test_info['equip_id']);
            
            if ($room_volume_query && $room_volume_query['room_volume']) {
                $room_volume = $room_volume_query['room_volume'];
            }
        }
        
        // Calculate ACPH if not stored
        if (!$calculated_acph && $room_volume > 0 && $grand_total_supply_cfm > 0) {
            // ACPH = (Total CFM * 60) / Room Volume (m³)
            // Convert CFM to m³/h: 1 CFM = 1.699 m³/h
            $totalM3H = $grand_total_supply_cfm * 1.699;
            $calculated_acph = round($totalM3H / $room_volume, 2);
        }
        
        // Generate PDFs using the extracted ACPH PDF generation functions
        $testConductedDate = $test_info['test_conducted_date'] ?? date('Y-m-d');
        
        // Generate Raw Data PDF
        error_log("Generating ACPH Raw Data PDF for finalization");
        $rawDataResult = generateACPHRawDataPDF($test_wf_id, $witnessData, $testConductedDate);
        
        if (!$rawDataResult['success']) {
            throw new Exception("Raw Data PDF generation failed: " . ($rawDataResult['error'] ?? 'Unknown error'));
        }
        
        $filename = $rawDataResult['filename'];
        $file_path = $rawDataResult['file_path'];
        
        // Generate Test Certificate PDF  
        error_log("Generating ACPH Test Certificate PDF for finalization");
        $certificateResult = generateACPHTestCertificatePDF($test_wf_id, $witnessData, $testConductedDate);
        
        if (!$certificateResult['success']) {
            throw new Exception("Test Certificate PDF generation failed: " . ($certificateResult['error'] ?? 'Unknown error'));
        }
        
        $certificate_filename = $certificateResult['filename'];
        $certificate_file_path = $certificateResult['file_path'];
        
        // Log success for both PDFs
        error_log("ACPH PDFs generated successfully for finalization: {$filename}, {$certificate_filename}");

            // PDF generation completed successfully using extracted functions
            // Now proceed with database operations
            
            // Add consolidated record to tbl_uploads for both ACPH PDFs
            $consolidated_upload_data = [
                'test_wf_id' => $test_wf_id,
                'val_wf_id' => $test_info['val_wf_id'] ?? null,
                'test_id' => $test_info['test_id'] ?? null,
                'upload_path_raw_data' => "../../uploads/{$filename}",
                'upload_path_test_certificate' => "../../uploads/{$certificate_filename}",
                'upload_type' => 'acph_test_documents',
                'uploaded_by' => $_SESSION['user_id'],
                'uploaded_datetime' => date('Y-m-d H:i:s'),
                'upload_status' => 'Active'
            ];
            
            $upload_id = DB::insert('tbl_uploads', $consolidated_upload_data);
            
            if ($upload_id) {
                error_log("SUCCESS: Created consolidated upload record (ID: $upload_id) for test_wf_id: $test_wf_id");
            } else {
                error_log("ERROR: Failed to create consolidated upload record for test_wf_id: $test_wf_id");
                throw new Exception("Failed to create upload record");
            }
            
            // Upload instrument calibration certificates for all tagged instruments
            $cert_upload_results = uploadInstrumentCalibrationCertificates($test_wf_id, $test_info);
            error_log("Instrument calibration certificate upload results: " . json_encode($cert_upload_results));
        
        } else {
            // In offline mode, skip PDF generation
            error_log("Skipping PDF generation for offline mode test_wf_id: {$test_wf_id}");
            
            // Set default values for variables that would have been set by PDF generation
            $upload_id = null;
            $filename = 'N/A (Offline Mode)';
            $certificate_filename = 'N/A (Offline Mode)';
            $cert_upload_results = ['message' => 'Skipped in offline mode'];
        }
        
        // Update test conducted date and test performer in tbl_test_schedules_tracking
        $update_data = [
            'test_conducted_date' => date('Y-m-d'),
            'test_performed_by' => $_SESSION['user_id']
        ];
        
        $rows_updated = DB::update('tbl_test_schedules_tracking', $update_data, 'test_wf_id=%s', $test_wf_id);
        
        if ($rows_updated > 0) {
            error_log("Updated test_conducted_date and test_performed_by for test_wf_id: {$test_wf_id}");
        } else {
            error_log("Warning: No rows updated for test_wf_id: {$test_wf_id}");
        }
        
        // Add record to tbl_test_finalisation_details
        $finalisation_data = [
            'test_wf_id' => $test_wf_id,
            'test_finalised_on' => date('Y-m-d H:i:s'),
            'test_finalised_by' => $_SESSION['user_id'],
            'status' => 'Active',
            'creation_datetime' => date('Y-m-d H:i:s')
        ];
        
        $finalisation_id = DB::insert('tbl_test_finalisation_details', $finalisation_data);
        
        // Commit transaction
        DB::query("COMMIT");
        
        $result = [
            'upload_id' => $upload_id,
            'finalisation_id' => $finalisation_id,
            'filename' => $filename
        ];
        
        // Clear output buffer and send success response
        ob_end_clean();
        
        $success_message = $skip_pdf_generation ? 
            'Test data finalized successfully (offline mode - PDFs not generated).' :
            'Test data finalized successfully. Raw data PDF and Test Certificate generated.';
            
        echo json_encode([
            'success' => true,
            'message' => $success_message,
            'offline_mode' => $skip_pdf_generation,
            'data' => $result
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        DB::query("ROLLBACK");
        throw $e;
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Upload instrument calibration certificates for all instruments tagged with the test
 * @param string $test_wf_id Test workflow ID
 * @param array $test_info Test information array
 * @return array Upload results with success/failure counts
 */
function uploadInstrumentCalibrationCertificates($test_wf_id, $test_info) {
    try {
        $results = [
            'total_instruments' => 0,
            'successful_uploads' => 0,
            'failed_uploads' => 0,
            'no_certificate' => 0,
            'details' => []
        ];
        
        // Get all instruments tagged with this test
        $tagged_instruments = DB::query("
            SELECT DISTINCT 
                ti.instrument_id,
                i.instrument_type,
                i.serial_number
            FROM test_instruments ti
            JOIN instruments i ON ti.instrument_id = i.instrument_id  
            WHERE ti.test_val_wf_id = %s 
            AND ti.is_active = 1
        ", $test_wf_id);
        
        if (empty($tagged_instruments)) {
            error_log("No tagged instruments found for test_wf_id: $test_wf_id");
            return $results;
        }
        
        $results['total_instruments'] = count($tagged_instruments);
        error_log("Found {$results['total_instruments']} tagged instruments for test_wf_id: $test_wf_id");
        
        foreach ($tagged_instruments as $instrument) {
            $instrument_id = $instrument['instrument_id'];
            
            try {
                // Get master certificate path from instruments table
                $instrument_cert = DB::queryFirstRow("
                    SELECT master_certificate_path
                    FROM instruments 
                    WHERE instrument_id = %s
                ", $instrument_id);
                
                if (!$instrument_cert || empty($instrument_cert['master_certificate_path'])) {
                    error_log("No master certificate path found for instrument: $instrument_id");
                    $results['no_certificate']++;
                    $results['details'][] = [
                        'instrument_id' => $instrument_id,
                        'status' => 'no_certificate',
                        'message' => 'No master certificate path found'
                    ];
                    continue;
                }
                
                // Create upload record for this instrument's calibration certificate
                $cert_upload_data = [
                    'test_wf_id' => $test_wf_id,
                    'val_wf_id' => $test_info['val_wf_id'] ?? null,
                    'test_id' => $test_info['test_id'] ?? null,
                    'upload_path_master_certificate' => $instrument_cert['master_certificate_path'],
                    'upload_type' => 'instrument_calibration_certificate',
                    'uploaded_by' => $_SESSION['user_id'],
                    'uploaded_datetime' => date('Y-m-d H:i:s'),
                    'upload_status' => 'Active'
                ];
                
                $cert_upload_id = DB::insert('tbl_uploads', $cert_upload_data);
                
                if ($cert_upload_id) {
                    $results['successful_uploads']++;
                    $results['details'][] = [
                        'instrument_id' => $instrument_id,
                        'upload_id' => $cert_upload_id,
                        'status' => 'success',
                        'certificate_path' => $instrument_cert['master_certificate_path']
                    ];
                    error_log("SUCCESS: Uploaded calibration certificate for instrument $instrument_id (upload_id: $cert_upload_id)");
                } else {
                    $results['failed_uploads']++;
                    $results['details'][] = [
                        'instrument_id' => $instrument_id,
                        'status' => 'failed',
                        'message' => 'Database insert failed'
                    ];
                    error_log("ERROR: Failed to create upload record for instrument $instrument_id calibration certificate");
                }
                
            } catch (Exception $e) {
                $results['failed_uploads']++;
                $results['details'][] = [
                    'instrument_id' => $instrument_id,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                error_log("ERROR: Exception uploading calibration certificate for instrument $instrument_id: " . $e->getMessage());
            }
        }
        
        error_log("Instrument calibration certificate upload summary for test_wf_id $test_wf_id: " . 
                  "Total: {$results['total_instruments']}, Success: {$results['successful_uploads']}, " .
                  "Failed: {$results['failed_uploads']}, No Certificate: {$results['no_certificate']}");
        
        return $results;
        
    } catch (Exception $e) {
        error_log("ERROR: Exception in uploadInstrumentCalibrationCertificates: " . $e->getMessage());
        return [
            'total_instruments' => 0,
            'successful_uploads' => 0,
            'failed_uploads' => 0,
            'no_certificate' => 0,
            'error' => $e->getMessage()
        ];
    }
}
?>