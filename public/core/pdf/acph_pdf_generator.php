<?php
/**
 * ACPH Test-Specific PDF Generation Functions
 * 
 * Reusable functions for generating ACPH Raw Data and Test Certificate PDFs
 * Used by both test finalization and document approval workflows
 */

require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../config/db.class.php');

/**
 * Helper function to get instrument summary for a filter
 */
function getInstrumentSummaryForFilter($filter, $instruments_lookup) {
    if (empty($filter['reading_instruments'])) {
        return 'N/A';
    }
    
    $used_instruments = [];
    foreach ($filter['reading_instruments'] as $reading_key => $instrument_id) {
        if (!empty($instrument_id) && isset($instruments_lookup[$instrument_id])) {
            $instrument = $instruments_lookup[$instrument_id];
            $instrument_display = $instrument['instrument_id'];
            if (!in_array($instrument_display, $used_instruments)) {
                $used_instruments[] = htmlspecialchars($instrument_display);
            }
        }
    }
    
    if (empty($used_instruments)) {
        return 'N/A';
    } else if (count($used_instruments) == 1) {
        return $used_instruments[0];
    } else {
        return implode(', ', $used_instruments);
    }
}

/**
 * Generate ACPH Raw Data PDF
 * 
 * @param string $testWfId Test workflow ID
 * @param array $witnessData Witness/finalization details
 * @param string $testConductedDate Test conducted date
 * @return array Result with filename and file path
 */
function generateACPHRawDataPDF($testWfId, $witnessData = null, $testConductedDate = '') {
    try {
        require_once(dirname(__FILE__) . '/../../vendor/autoload.php');
        
        // Create filename with timestamp
        $timestamp = time();
        $filename = "RawData-{$testWfId}-{$timestamp}.pdf";
        $file_path = dirname(__FILE__) . "/../../uploads/{$filename}";
        
        // Get comprehensive test and workflow information
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
        ", $testWfId);
        
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
        
        // Get ACPH filter data (stored as individual filter records)
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
        ", $testWfId);
        
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
        ", $testWfId);
        
        // Create an instrument lookup array for easier access
        $instruments_lookup = [];
        foreach ($instruments_info as $instrument) {
            $instruments_lookup[$instrument['instrument_id']] = $instrument;
        }
        
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
        
        // Process ACPH data from individual filter records
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
        
        // Create header HTML for mPDF - use base64 encoded image for reliability
        $logo_paths = [
            dirname(__FILE__) . '/../../assets/images/logo.png',
            '/opt/homebrew/var/www/provalnxt/public/assets/images/logo.png',
            $_SERVER['DOCUMENT_ROOT'] . '/assets/images/logo.png'
        ];
        
        $logo_base64 = '';
        $working_logo_path = '';
        
        foreach ($logo_paths as $path) {
            error_log("Checking logo path: " . $path);
            if (file_exists($path)) {
                $working_logo_path = $path;
                $image_data = file_get_contents($path);
                $logo_base64 = 'data:image/png;base64,' . base64_encode($image_data);
                error_log("Successfully created base64 logo from: " . $working_logo_path);
                break;
            }
        }
        
        if (!$logo_base64) {
            error_log("Could not create base64 logo - logo will not be displayed");
            $logo_html = '<span style="font-size: 14pt; font-weight: bold; color: #333;">Goa</span>'; // Text only if no logo
        } else {
            $logo_html = '<img src="' . $logo_base64 . '" alt="Company Logo" style="height: 30px; margin-right: 10px;" /><span style="font-size: 14pt; font-weight: bold; color: #333;">Goa</span>';
        }
        
        $header_html = '<table width="100%" style="border: none; border-collapse: collapse;">
            <tr>
                <td width="70%" style="text-align: left; vertical-align: top; border: none; padding: 0;">
                    <h1 style="color: #333; font-size: 16pt; margin: 0;">Test Raw Data Report</h1>
                </td>
                <td width="30%" style="text-align: right; vertical-align: middle; border: none; padding: 0;">
                    ' . $logo_html . '
                </td>
            </tr>
        </table>';

        // Create HTML with all required content and styling
        $simple_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; font-size: 10pt; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .results-table th, .results-table td { font-size: 8pt; padding: 4px; text-align: center; }
        h1 { color: #333; text-align: center; font-size: 16pt; margin: 10px 0; }
        h2 { color: #666; border-bottom: 2px solid purple; padding-bottom: 5px; font-size: 12pt; margin-top: 20px; }
        .highlight { background-color: #ffffcc; font-weight: bold; }
        .summary-row { background-color: #e9ecef; font-weight: bold; }
    </style>
</head>
<body>
    
    <h2>Test Details</h2>
    <table>
        <tr><td><strong>Test Purpose:</strong></td><td>' . htmlspecialchars($test_info['test_purpose'] ?? 'Air Changes Per Hour Validation') . '</td></tr>
        <tr><td><strong>Test Date:</strong></td><td>' . (!empty($testConductedDate) ? htmlspecialchars(date('d.m.Y', strtotime($testConductedDate))) : 'N/A') . '</td></tr>
        <tr><td><strong>Test Workflow ID:</strong></td><td>' . htmlspecialchars($testWfId) . ' (part of ' . htmlspecialchars($test_info['val_wf_id'] ?? 'N/A') . ')</td></tr>
        <tr><td><strong>Equipment:</strong></td><td>' . htmlspecialchars($test_info['equipment_code'] ?? 'N/A') . ' - ' . htmlspecialchars($test_info['unit_name'] ?? 'N/A') . '</td></tr>
    </table>
    
    <h2>Instrument Details</h2>';
    
    if (!empty($instruments_info)) {
        $simple_html .= '
    <table class="results-table">
        <thead>
            <tr>
                <th>Instrument Type</th>
                <th>Instrument ID</th>
                <th>Serial Number</th>
                <th>Calibration Status</th>
                <th>Calibrated On</th>
                <th>Calibration Due</th>
                <th>Added Date</th>
                <th>Added By</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($instruments_info as $instrument) {
            $calibrated_on = !empty($instrument['calibrated_on']) ? date('d.m.Y', strtotime($instrument['calibrated_on'])) : 'N/A';
            $calibration_due = !empty($instrument['calibration_due_on']) ? date('d.m.Y', strtotime($instrument['calibration_due_on'])) : 'N/A';
            $added_date = !empty($instrument['added_date']) ? date('d.m.Y', strtotime($instrument['added_date'])) : 'N/A';
            
            $simple_html .= '
            <tr>
                <td>' . htmlspecialchars($instrument['instrument_type'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($instrument['instrument_id'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($instrument['serial_number'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($instrument['calibration_status'] ?? 'N/A') . '</td>
                <td>' . $calibrated_on . '</td>
                <td>' . $calibration_due . '</td>
                <td>' . $added_date . '</td>
                <td>' . htmlspecialchars($instrument['added_by_name'] ?? 'N/A') . '</td>
            </tr>';
        }
        
        $simple_html .= '
        </tbody>
    </table>';
    } else {
        $simple_html .= '<p>No instruments found for this test.</p>';
    }
    
    $simple_html .= '
    <h2>Calculations Summary</h2>
    <table>
        <tr><td><strong>Room Volume (m³):</strong></td><td class="highlight">' . ($room_volume ? number_format($room_volume, 2) : 'N/A') . '</td></tr>
        <tr><td><strong>Total Supply CFM:</strong></td><td class="highlight">' . ($grand_total_supply_cfm ? number_format($grand_total_supply_cfm, 2) : 'N/A') . '</td></tr>
        <tr><td><strong>Calculated ACPH:</strong></td><td class="highlight">' . ($calculated_acph ? number_format($calculated_acph, 2) : 'N/A') . '</td></tr>
    </table>';
    
    // Add filter data if available
    if (!empty($filters)) {
        $simple_html .= '<pagebreak /><h2>Test Results</h2>
        <table class="results-table">
            <thead>
                <tr>
                    <th rowspan="2">Room Name</th>
                    <th rowspan="2">Area Classification</th>
                    <th rowspan="2">Instrument ID</th>
                    <th rowspan="2">Filter Code</th>
                    <th colspan="5">Observed Readings in fpm</th>
                    <th rowspan="2">Avg in fpm</th>
                    <th rowspan="2">Cell Area (AC)</th>
                    <th rowspan="2">Flow Rate</th>
                    <th rowspan="2">Total Flow Rate</th>
                    <th rowspan="2">Entered By</th>
                    <th rowspan="2">Entry Date</th>
                </tr>
                <tr>
                    <th>R1</th>
                    <th>R2</th>
                    <th>R3</th>
                    <th>R4</th>
                    <th>R5</th>
                </tr>
            </thead>
            <tbody>';
            
        foreach ($filters as $filter_id => $filter) {
            // Format entry date
            $entry_date = '-';
            if (!empty($filter['entered_date'])) {
                try {
                    $date = new DateTime($filter['entered_date']);
                    $entry_date = $date->format('d.m.Y H:i');
                } catch (Exception $e) {
                    $entry_date = 'Invalid Date';
                }
            }
            
            $simple_html .= '<tr>
                <td>' . htmlspecialchars($room_name) . '</td>
                <td>' . htmlspecialchars($area_classification) . '</td>
                <td>' . getInstrumentSummaryForFilter($filter, $instruments_lookup) . '</td>
                <td>' . htmlspecialchars($filter['filter_code'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($filter['readings']['reading_1'] ?? '-') . '</td>
                <td>' . htmlspecialchars($filter['readings']['reading_2'] ?? '-') . '</td>
                <td>' . htmlspecialchars($filter['readings']['reading_3'] ?? '-') . '</td>
                <td>' . htmlspecialchars($filter['readings']['reading_4'] ?? '-') . '</td>
                <td>' . htmlspecialchars($filter['readings']['reading_5'] ?? '-') . '</td>
                <td class="highlight">' . number_format($filter['average_reading'] ?? 0, 2) . '</td>
                <td>' . number_format($filter['cell_area'] ?? 0, 2) . '</td>
                <td>' . number_format($filter['flow_rate'] ?? 0, 2) . '</td>
                <td class="highlight">' . number_format($filter['total_flow_rate'] ?? 0, 2) . '</td>
                <td>' . htmlspecialchars($filter['entered_by'] ?? 'N/A') . '</td>
                <td>' . $entry_date . '</td>
            </tr>';
        }
        
        // Add Total Flow Rate Sum row
        $simple_html .= '<tr class="summary-row">
                <td colspan="12" style="text-align: right; font-weight: bold;">Total Flow Rate Sum:</td>
                <td class="highlight" style="font-weight: bold;">' . number_format($total_flow_rate_sum, 2) . '</td>
                <td colspan="2"></td>
            </tr>';
            
        $simple_html .= '</tbody></table>';
    } else {
        $simple_html .= '<h2>Test Results</h2><p>No filter data available for this test.</p>';
    }
    
    // Add Test Performance and Witness Details section
    if ($witnessData) {
        $test_done_by = htmlspecialchars($witnessData['finalised_by_name'] ?? 'N/A');
        $test_done_on = 'N/A';
        if (!empty($witnessData['test_finalised_on'])) {
            $test_done_on = date('d.m.Y H:i', strtotime($witnessData['test_finalised_on']));
        }
        
        $test_witnessed_by = htmlspecialchars($witnessData['witness_name'] ?? 'N/A');
        $test_witnessed_on = 'N/A';
        if (!empty($witnessData['test_witnessed_on'])) {
            $test_witnessed_on = date('d.m.Y H:i', strtotime($witnessData['test_witnessed_on']));
        }
        
        $simple_html .= '
    <h2>Test Performance and Witness Details</h2>
    <table>
        <tr><td><strong>Test Done By:</strong></td><td>' . $test_done_by . '</td></tr>
        <tr><td><strong>Test Done On:</strong></td><td>' . $test_done_on . '</td></tr>
        <tr><td><strong>Test Witnessed By:</strong></td><td>' . $test_witnessed_by . '</td></tr>
        <tr><td><strong>Test Witnessed On:</strong></td><td>' . $test_witnessed_on . '</td></tr>
    </table>';
    }
    
    $simple_html .= '
</body>
</html>';

    // Create footer HTML
    $footer_html = '<table width="100%" style="font-size: 9pt; color: #666; border: none; border-collapse: collapse; margin-top: 10px;">
        <tr>
            <td width="70%" style="text-align: left; border: none; padding: 0;">
                <strong>Generated On:</strong> ' . date('d.m.Y H:i:s') . ' | 
                <strong>Requested By:</strong> ' . htmlspecialchars(($_SESSION['user_name'] ?? 'System')) . '
            </td>
            <td width="30%" style="text-align: right; border: none; padding: 0;">
                <strong>Page {PAGENO} of {nbpg}</strong>
            </td>
        </tr>
    </table>';
        
        // Create mPDF instance with header and footer configuration
        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4-L',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 30,
            'margin_bottom' => 25,
            'margin_header' => 10,
            'margin_footer' => 10,
            'default_font' => 'Arial',
            'default_font_size' => 9,
            'mode' => 'utf-8'
        ]);
        
        // Set the header and footer
        $mpdf->SetHTMLHeader($header_html);
        $mpdf->SetHTMLFooter($footer_html);
        
        // Debug: Save HTML to file for inspection
        $html_debug_path = dirname(__FILE__) . "/../../uploads/debug_html_{$timestamp}.html";
        file_put_contents($html_debug_path, $simple_html);
        
        // Write the simple HTML to PDF
        $mpdf->WriteHTML($simple_html);
        
        // Add witness page if witness data exists and has witness
        if ($witnessData && !empty($witnessData['witness_name'])) {
            // Add new page for witness details
            $mpdf->AddPage();
            
            // Create witness page HTML
            $witness_html = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 12pt; }
                    .witness-title { text-align: center; font-size: 16pt; font-weight: bold; margin-bottom: 30px; color: #333; }
                    .witness-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    .witness-table th, .witness-table td { 
                        border: 1px solid #333; 
                        padding: 12px; 
                        text-align: left; 
                        vertical-align: middle;
                    }
                    .witness-table th { 
                        background-color: #f0f0f0; 
                        font-weight: bold; 
                        text-align: center;
                    }
                    .witness-table .label-cell { 
                        background-color: #f9f9f9; 
                        font-weight: bold; 
                        width: 40%;
                    }
                    .witness-table .value-cell { 
                        background-color: #ffffff; 
                        width: 60%;
                    }
                </style>
            </head>
            <body>
                <div class="witness-title">Test Witness Details</div>
                
                <table class="witness-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell">Test Done By</td>
                            <td class="value-cell">' . htmlspecialchars($witnessData['finalised_by_name'] ?? 'N/A') . '</td>
                        </tr>
                        <tr>
                            <td class="label-cell">Test Done On</td>
                            <td class="value-cell">' . ($witnessData['test_finalised_on'] ? date('d.m.Y H:i:s', strtotime($witnessData['test_finalised_on'])) : 'N/A') . '</td>
                        </tr>
                        <tr>
                            <td class="label-cell">Test Witnessed By</td>
                            <td class="value-cell">' . htmlspecialchars($witnessData['witness_name'] ?? 'N/A') . '</td>
                        </tr>
                        <tr>
                            <td class="label-cell">Test Witnessed On</td>
                            <td class="value-cell">' . ($witnessData['test_witnessed_on'] ? date('d.m.Y H:i:s', strtotime($witnessData['test_witnessed_on'])) : 'N/A') . '</td>
                        </tr>
                    </tbody>
                </table>
            </body>
            </html>';
            
            // Write witness page HTML to PDF
            $mpdf->WriteHTML($witness_html);
            
            error_log("Witness page added to PDF for test_wf_id: {$testWfId}");
        }
        
        // Save PDF to file
        $pdf_string = $mpdf->Output('', 'S');
        file_put_contents($file_path, $pdf_string);
        
        // Log success
        error_log("ACPH Raw Data PDF generated successfully: {$filename}");
        error_log("HTML debug file: debug_html_{$timestamp}.html");
        
        return [
            'success' => true,
            'filename' => $filename,
            'file_path' => $file_path,
            'upload_path' => "../../uploads/{$filename}"
        ];
        
    } catch (Exception $e) {
        error_log("ACPH Raw Data PDF Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Generate ACPH Test Certificate PDF
 * 
 * @param string $testWfId Test workflow ID
 * @param array $witnessData Witness/finalization details
 * @param string $testConductedDate Test conducted date
 * @return array Result with filename and file path
 */
function generateACPHTestCertificatePDF($testWfId, $witnessData = null, $testConductedDate = '') {
    try {
        require_once(dirname(__FILE__) . '/../../vendor/autoload.php');
        
        // Create filename with timestamp
        $timestamp = time();
        $filename = "TestCertificate-{$testWfId}-{$timestamp}.pdf";
        $file_path = dirname(__FILE__) . "/../../uploads/{$filename}";
        
        // Reuse most of the logic from Raw Data PDF generation
        // Get comprehensive test and workflow information (same as Raw Data)
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
        ", $testWfId);
        
        // Get room info, instruments, filters (same logic as Raw Data)
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
        
        $room_name = $room_info['room_loc_name'] ?? 'N/A';
        $area_classification = $room_info['area_classification'] ?? 'N/A';
        
        // Get instruments and filter data (same as Raw Data)
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
        ", $testWfId);
        
        $instruments_lookup = [];
        foreach ($instruments_info as $instrument) {
            $instruments_lookup[$instrument['instrument_id']] = $instrument;
        }
        
        // Get filter data (same processing as Raw Data)
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
        ", $testWfId);
        
        $filters = [];
        $room_volume = null;
        $grand_total_supply_cfm = 0;
        $calculated_acph = null;
        $total_flow_rate_sum = 0;
        
        // Process filters (same logic as Raw Data)
        foreach ($filter_data_records as $record) {
            $filter_data = json_decode($record['data_json'], true);
            if (!$filter_data) continue;
            
            preg_match('/acph_filter_(\d+)/', $record['section_type'], $matches);
            $filter_id = $matches[1] ?? $record['filter_id'] ?? 'unknown';
            
            $readings = [];
            $reading_instruments = [];
            $reading_sum = 0;
            $reading_count = 0;
            
            if (isset($filter_data['readings']) && is_array($filter_data['readings'])) {
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
                    }
                }
            } else {
                for ($i = 1; $i <= 5; $i++) {
                    $reading_value = $filter_data["reading_$i"] ?? '';
                    $readings["reading_$i"] = $reading_value;
                    $reading_instruments["reading_$i"] = '';
                    if (is_numeric($reading_value) && $reading_value > 0) {
                        $reading_sum += floatval($reading_value);
                        $reading_count++;
                    }
                }
            }
            
            $average_reading = 0;
            if (isset($filter_data['average']) && is_numeric($filter_data['average'])) {
                $average_reading = floatval($filter_data['average']);
            } elseif ($reading_count > 0) {
                $average_reading = round($reading_sum / $reading_count, 2);
            }
            
            $cell_area = floatval($filter_data['cell_area'] ?? 0);
            $flow_rate = floatval($filter_data['flow_rate'] ?? 0);
            $total_flow_rate_sum += $flow_rate;
            
            $filters[$filter_id] = [
                'filter_code' => $filter_data['filter_code'] ?? "AHU-01/THF/0.3μm/0$filter_id/A",
                'cell_area' => $cell_area,
                'flow_rate' => round($flow_rate, 2),
                'reading_instruments' => $reading_instruments,
                'average_reading' => $average_reading,
                'total_flow_rate' => round($flow_rate, 2)
            ];
            
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
            $totalM3H = $grand_total_supply_cfm * 1.699;
            $calculated_acph = round($totalM3H / $room_volume, 2);
        }
        
        // Create logo (same as Raw Data)
        $logo_paths = [
            dirname(__FILE__) . '/../../assets/images/logo.png',
            '/opt/homebrew/var/www/provalnxt/public/assets/images/logo.png',
            $_SERVER['DOCUMENT_ROOT'] . '/assets/images/logo.png'
        ];
        
        $logo_base64 = '';
        foreach ($logo_paths as $path) {
            if (file_exists($path)) {
                $image_data = file_get_contents($path);
                $logo_base64 = 'data:image/png;base64,' . base64_encode($image_data);
                break;
            }
        }
        
        $logo_html = $logo_base64 ? 
            '<img src="' . $logo_base64 . '" alt="Company Logo" style="height: 30px; margin-right: 10px;" /><span style="font-size: 14pt; font-weight: bold; color: #333;">Goa</span>' :
            '<span style="font-size: 14pt; font-weight: bold; color: #333;">Goa</span>';
        
        // Create Test Certificate HTML (modified for certificate - removing specified columns)
        $certificate_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; font-size: 10pt; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .results-table th, .results-table td { font-size: 8pt; padding: 4px; text-align: center; }
        h1 { color: #333; text-align: center; font-size: 16pt; margin: 10px 0; }
        h2 { color: #666; border-bottom: 2px solid purple; padding-bottom: 5px; font-size: 12pt; margin-top: 20px; }
        .highlight { background-color: #ffffcc; font-weight: bold; }
        .summary-row { background-color: #e9ecef; font-weight: bold; }
    </style>
</head>
<body>
    
    <h2>Test Details</h2>
    <table>
        <tr><td><strong>Test Purpose:</strong></td><td>' . htmlspecialchars($test_info['test_purpose'] ?? 'Air Changes Per Hour Validation') . '</td></tr>
        <tr><td><strong>Test Date:</strong></td><td>' . (!empty($testConductedDate) ? htmlspecialchars(date('d.m.Y', strtotime($testConductedDate))) : 'N/A') . '</td></tr>
        <tr><td><strong>Test Workflow ID:</strong></td><td>' . htmlspecialchars($testWfId) . ' (part of ' . htmlspecialchars($test_info['val_wf_id'] ?? 'N/A') . ')</td></tr>
        <tr><td><strong>Equipment:</strong></td><td>' . htmlspecialchars($test_info['equipment_code'] ?? 'N/A') . ' - ' . htmlspecialchars($test_info['unit_name'] ?? 'N/A') . '</td></tr>
    </table>
    
    <h2>Instrument Details</h2>';
    
    if (!empty($instruments_info)) {
        $certificate_html .= '
    <table class="results-table">
        <thead>
            <tr>
                <th>Instrument Type</th>
                <th>Instrument ID</th>
                <th>Serial Number</th>
                <th>Calibration Status</th>
                <th>Calibrated On</th>
                <th>Calibration Due</th>
                <th>Added Date</th>
                <th>Added By</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($instruments_info as $instrument) {
            $calibrated_on = !empty($instrument['calibrated_on']) ? date('d.m.Y', strtotime($instrument['calibrated_on'])) : 'N/A';
            $calibration_due = !empty($instrument['calibration_due_on']) ? date('d.m.Y', strtotime($instrument['calibration_due_on'])) : 'N/A';
            $added_date = !empty($instrument['added_date']) ? date('d.m.Y', strtotime($instrument['added_date'])) : 'N/A';
            
            $certificate_html .= '
            <tr>
                <td>' . htmlspecialchars($instrument['instrument_type'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($instrument['instrument_id'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($instrument['serial_number'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($instrument['calibration_status'] ?? 'N/A') . '</td>
                <td>' . $calibrated_on . '</td>
                <td>' . $calibration_due . '</td>
                <td>' . $added_date . '</td>
                <td>' . htmlspecialchars($instrument['added_by_name'] ?? 'N/A') . '</td>
            </tr>';
        }
        
        $certificate_html .= '
        </tbody>
    </table>';
    } else {
        $certificate_html .= '<p>No instruments found for this test.</p>';
    }
    
    $certificate_html .= '
    <h2>Calculations Summary</h2>
    <table>
        <tr><td><strong>Room Volume (m³):</strong></td><td class="highlight">' . ($room_volume ? number_format($room_volume, 2) : 'N/A') . '</td></tr>
        <tr><td><strong>Total Supply CFM:</strong></td><td class="highlight">' . ($grand_total_supply_cfm ? number_format($grand_total_supply_cfm, 2) : 'N/A') . '</td></tr>
        <tr><td><strong>Calculated ACPH:</strong></td><td class="highlight">' . ($calculated_acph ? number_format($calculated_acph, 2) : 'N/A') . '</td></tr>
    </table>';
    
    // Add filter data if available (modified for certificate - removing specified columns)
    if (!empty($filters)) {
        $certificate_html .= '<pagebreak /><h2>Test Results</h2>
        <table class="results-table">
            <thead>
                <tr>
                    <th>Room Name</th>
                    <th>Area Classification</th>
                    <th>Instrument ID</th>
                    <th>Filter Code</th>
                    <th>Avg in fpm</th>
                    <th>Cell Area (AC)</th>
                    <th>Flow Rate</th>
                    <th>Total Flow Rate</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($filters as $filter_id => $filter) {
            $certificate_html .= '<tr>
                <td>' . htmlspecialchars($room_name) . '</td>
                <td>' . htmlspecialchars($area_classification) . '</td>
                <td>' . getInstrumentSummaryForFilter($filter, $instruments_lookup) . '</td>
                <td>' . htmlspecialchars($filter['filter_code'] ?? 'N/A') . '</td>
                <td class="highlight">' . number_format($filter['average_reading'] ?? 0, 2) . '</td>
                <td>' . number_format($filter['cell_area'] ?? 0, 2) . '</td>
                <td>' . number_format($filter['flow_rate'] ?? 0, 2) . '</td>
                <td class="highlight">' . number_format($filter['total_flow_rate'] ?? 0, 2) . '</td>
            </tr>';
        }
        
        // Add Total Flow Rate Sum row
        $certificate_html .= '<tr class="summary-row">
                <td colspan="7" style="text-align: right; font-weight: bold;">Total Flow Rate Sum:</td>
                <td style="font-weight: bold; background-color: #ffffcc;">' . ($total_flow_rate_sum ? number_format($total_flow_rate_sum, 2) : '0.00') . '</td>
            </tr>';
            
        $certificate_html .= '</tbody></table>';
    } else {
        $certificate_html .= '<h2>Test Certificate</h2><p>No filter data available for this test.</p>';
    }
    
    // Add Test Performance and Witness Details section
    if ($witnessData) {
        $test_done_by = htmlspecialchars($witnessData['finalised_by_name'] ?? 'N/A');
        $test_done_on = 'N/A';
        if (!empty($witnessData['test_finalised_on'])) {
            $test_done_on = date('d.m.Y H:i', strtotime($witnessData['test_finalised_on']));
        }
        
        $test_witnessed_by = htmlspecialchars($witnessData['witness_name'] ?? 'N/A');
        $test_witnessed_on = 'N/A';
        if (!empty($witnessData['test_witnessed_on'])) {
            $test_witnessed_on = date('d.m.Y H:i', strtotime($witnessData['test_witnessed_on']));
        }
        
        $certificate_html .= '
    <h2>Test Performance and Witness Details</h2>
    <table>
        <tr><td><strong>Test Done By:</strong></td><td>' . $test_done_by . '</td></tr>
        <tr><td><strong>Test Done On:</strong></td><td>' . $test_done_on . '</td></tr>
        <tr><td><strong>Test Witnessed By:</strong></td><td>' . $test_witnessed_by . '</td></tr>
        <tr><td><strong>Test Witnessed On:</strong></td><td>' . $test_witnessed_on . '</td></tr>
    </table>';
    }
    
    $certificate_html .= '
</body>
</html>';

        // Create certificate PDF with same header and footer as Raw Data PDF
        $certificate_mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 30,
            'margin_bottom' => 25,
            'margin_header' => 10,
            'margin_footer' => 10
        ]);
        
        $certi_header_html = '<table width="100%" style="border: none; border-collapse: collapse;">
        <tr>
            <td width="70%" style="text-align: left; vertical-align: top; border: none; padding: 0;">
                <h1 style="color: #333; font-size: 16pt; margin: 0;">Test Certificate</h1>
            </td>
            <td width="30%" style="text-align: right; vertical-align: middle; border: none; padding: 0;">
                ' . $logo_html . '
            </td>
        </tr>
    </table>';

        $footer_html = '<table width="100%" style="font-size: 9pt; color: #666; border: none; border-collapse: collapse; margin-top: 10px;">
        <tr>
            <td width="70%" style="text-align: left; border: none; padding: 0;">
                <strong>Generated On:</strong> ' . date('d.m.Y H:i:s') . ' | 
                <strong>Requested By:</strong> ' . htmlspecialchars(($_SESSION['user_name'] ?? 'System')) . '
            </td>
            <td width="30%" style="text-align: right; border: none; padding: 0;">
                <strong>Page {PAGENO} of {nbpg}</strong>
            </td>
        </tr>
    </table>';

        $certificate_mpdf->SetHTMLHeader($certi_header_html);
        $certificate_mpdf->SetHTMLFooter($footer_html);
        
        // Write the certificate HTML to PDF
        $certificate_mpdf->WriteHTML($certificate_html);
        
        // Save certificate PDF to file
        $certificate_pdf_string = $certificate_mpdf->Output('', 'S');
        file_put_contents($file_path, $certificate_pdf_string);
        
        // Log success
        error_log("ACPH Test Certificate PDF generated successfully: {$filename}");
        
        return [
            'success' => true,
            'filename' => $filename,
            'file_path' => $file_path,
            'upload_path' => "../../uploads/{$filename}"
        ];
        
    } catch (Exception $e) {
        error_log("ACPH Test Certificate PDF Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function regenerateTestCertificateWithQAApproval($testWfId, $qaApprovalDetails, $fileRecord) {
    try {
        // Fetch witness data from database
        $witnessData = DB::queryFirstRow("
            SELECT 
                tfd.test_finalised_on,
                tfd.test_witnessed_on,
                tfd.witness_action,
                u1.user_name as finalised_by_name,
                u2.user_name as witness_name
            FROM tbl_test_finalisation_details tfd
            LEFT JOIN users u1 ON tfd.test_finalised_by = u1.user_id
            LEFT JOIN users u2 ON tfd.witness = u2.user_id
            WHERE tfd.test_wf_id = %s
            AND tfd.status = 'Active'
            ORDER BY tfd.creation_datetime DESC
            LIMIT 1
        ", $testWfId);
        
        // Get test conducted date
        $testConductedDate = '';
        $testInfo = DB::queryFirstRow("
            SELECT test_conducted_date 
            FROM tbl_test_schedules_tracking 
            WHERE test_wf_id = %s
        ", $testWfId);
        
        if ($testInfo && $testInfo['test_conducted_date']) {
            $testConductedDate = $testInfo['test_conducted_date'];
        }
        
        // Log witness data for debugging
        error_log("Witness data fetched for $testWfId: " . json_encode($witnessData));
        
        // Create a modified version of generateACPHTestCertificatePDF that includes QA approval details
        // IMPORTANT: Pass the fetched witnessData, not null!
        return generateACPHTestCertificateWithQAApproval($testWfId, $witnessData, $testConductedDate, $qaApprovalDetails);
        
    } catch (Exception $e) {
        error_log("QA Approval Test Certificate PDF Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function generateACPHTestCertificateWithQAApproval($testWfId, $witnessData = null, $testConductedDate = '', $qaApprovalDetails = null) {
    try {
        require_once(dirname(__FILE__) . '/../../vendor/autoload.php');
        
        // Create filename with QA suffix
        $timestamp = time();
        $random_suffix = substr(md5(uniqid()), 0, 8);
        $filename = "TestCertificate-{$testWfId}-{$timestamp}-QA-{$random_suffix}.pdf";
        $file_path = dirname(__FILE__) . "/../../uploads/{$filename}";
        
        // Get comprehensive test and workflow information (same as existing function)
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
        ", $testWfId);
        
        // Get room info, instruments, filters (same logic as existing function)
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
        
        $room_name = $room_info['room_loc_name'] ?? 'N/A';
        $area_classification = $room_info['area_classification'] ?? 'N/A';
        
        // Get instruments and filter data (same as existing function)
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
        ", $testWfId);
        
        $instruments_lookup = [];
        foreach ($instruments_info as $instrument) {
            $instruments_lookup[$instrument['instrument_id']] = $instrument;
        }
        
        // Get filter data (same processing as existing function)
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
        ", $testWfId);
        
        $filters = [];
        $room_volume = null;
        $grand_total_supply_cfm = 0;
        $calculated_acph = null;
        $total_flow_rate_sum = 0;
        
        // Process filters (same logic as existing function)
        foreach ($filter_data_records as $record) {
            $filter_data = json_decode($record['data_json'], true);
            if (!$filter_data) continue;
            
            preg_match('/acph_filter_(\\d+)/', $record['section_type'], $matches);
            $filter_id = $matches[1] ?? $record['filter_id'] ?? 'unknown';
            
            $readings = [];
            $reading_instruments = [];
            $reading_sum = 0;
            $reading_count = 0;
            
            if (isset($filter_data['readings']) && is_array($filter_data['readings'])) {
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
                    }
                }
            } else {
                for ($i = 1; $i <= 5; $i++) {
                    $reading_value = $filter_data["reading_$i"] ?? '';
                    $readings["reading_$i"] = $reading_value;
                    $reading_instruments["reading_$i"] = '';
                    if (is_numeric($reading_value) && $reading_value > 0) {
                        $reading_sum += floatval($reading_value);
                        $reading_count++;
                    }
                }
            }
            
            $average_reading = 0;
            if (isset($filter_data['average']) && is_numeric($filter_data['average'])) {
                $average_reading = floatval($filter_data['average']);
            } elseif ($reading_count > 0) {
                $average_reading = round($reading_sum / $reading_count, 2);
            }
            
            $cell_area = floatval($filter_data['cell_area'] ?? 0);
            $flow_rate = floatval($filter_data['flow_rate'] ?? 0);
            $total_flow_rate_sum += $flow_rate;
            
            $filters[$filter_id] = [
                'filter_code' => $filter_data['filter_code'] ?? "AHU-01/THF/0.3μm/0$filter_id/A",
                'cell_area' => $cell_area,
                'flow_rate' => round($flow_rate, 2),
                'reading_instruments' => $reading_instruments,
                'average_reading' => $average_reading,
                'total_flow_rate' => round($flow_rate, 2)
            ];
            
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
        
        if ($grand_total_supply_cfm == 0 && $total_flow_rate_sum > 0) {
            $grand_total_supply_cfm = round($total_flow_rate_sum, 2);
        }
        
        // Get room volume from equipment if not in filter data
        if (!$room_volume && $room_info) {
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
            $totalM3H = $grand_total_supply_cfm * 1.699;
            $calculated_acph = round($totalM3H / $room_volume, 2);
        }
        
        // Create logo (same as existing function)
        $logo_paths = [
            dirname(__FILE__) . '/../../assets/images/logo.png',
            '/opt/homebrew/var/www/provalnxt/public/assets/images/logo.png',
            $_SERVER['DOCUMENT_ROOT'] . '/assets/images/logo.png'
        ];
        
        $logo_base64 = '';
        foreach ($logo_paths as $path) {
            if (file_exists($path)) {
                $image_data = file_get_contents($path);
                $logo_base64 = 'data:image/png;base64,' . base64_encode($image_data);
                break;
            }
        }
        
        $logo_html = $logo_base64 ? 
            '<img src="' . $logo_base64 . '" alt="Company Logo" style="height: 30px; margin-right: 10px;" /><span style="font-size: 14pt; font-weight: bold; color: #333;">Goa</span>' :
            '<span style="font-size: 14pt; font-weight: bold; color: #333;">Goa</span>';
        
        // Create Test Certificate HTML with NEW separate QA approval section
        $certificate_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; font-size: 10pt; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .results-table th, .results-table td { font-size: 8pt; padding: 4px; text-align: center; }
        h1 { color: #333; text-align: center; font-size: 16pt; margin: 10px 0; }
        h2 { color: #666; border-bottom: 2px solid purple; padding-bottom: 5px; font-size: 12pt; margin-top: 20px; }
        .highlight { background-color: #ffffcc; font-weight: bold; }
        .summary-row { background-color: #e9ecef; font-weight: bold; }
    </style>
</head>
<body>
    
    <h2>Test Details</h2>
    <table>
        <tr><td><strong>Test Purpose:</strong></td><td>' . htmlspecialchars($test_info['test_purpose'] ?? 'Air Changes Per Hour Validation') . '</td></tr>
        <tr><td><strong>Test Date:</strong></td><td>' . (!empty($testConductedDate) ? htmlspecialchars(date('d.m.Y', strtotime($testConductedDate))) : 'N/A') . '</td></tr>
        <tr><td><strong>Test Workflow ID:</strong></td><td>' . htmlspecialchars($testWfId) . ' (part of ' . htmlspecialchars($test_info['val_wf_id'] ?? 'N/A') . ')</td></tr>
        <tr><td><strong>Equipment:</strong></td><td>' . htmlspecialchars($test_info['equipment_code'] ?? 'N/A') . ' - ' . htmlspecialchars($test_info['unit_name'] ?? 'N/A') . '</td></tr>
    </table>
    
    <h2>Instrument Details</h2>';
    
    if (!empty($instruments_info)) {
        $certificate_html .= '
    <table class="results-table">
        <thead>
            <tr>
                <th>Instrument Type</th>
                <th>Instrument ID</th>
                <th>Serial Number</th>
                <th>Calibration Status</th>
                <th>Calibrated On</th>
                <th>Calibration Due</th>
                <th>Added Date</th>
                <th>Added By</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($instruments_info as $instrument) {
            $calibrated_on = !empty($instrument['calibrated_on']) ? date('d.m.Y', strtotime($instrument['calibrated_on'])) : 'N/A';
            $calibration_due = !empty($instrument['calibration_due_on']) ? date('d.m.Y', strtotime($instrument['calibration_due_on'])) : 'N/A';
            $added_date = !empty($instrument['added_date']) ? date('d.m.Y', strtotime($instrument['added_date'])) : 'N/A';
            
            $certificate_html .= '
            <tr>
                <td>' . htmlspecialchars($instrument['instrument_type'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($instrument['instrument_id'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($instrument['serial_number'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($instrument['calibration_status'] ?? 'N/A') . '</td>
                <td>' . $calibrated_on . '</td>
                <td>' . $calibration_due . '</td>
                <td>' . $added_date . '</td>
                <td>' . htmlspecialchars($instrument['added_by_name'] ?? 'N/A') . '</td>
            </tr>';
        }
        
        $certificate_html .= '
        </tbody>
    </table>';
    } else {
        $certificate_html .= '<p>No instruments found for this test.</p>';
    }
    
    $certificate_html .= '
    <h2>Calculations Summary</h2>
    <table>
        <tr><td><strong>Room Volume (m³):</strong></td><td class="highlight">' . ($room_volume ? number_format($room_volume, 2) : 'N/A') . '</td></tr>
        <tr><td><strong>Total Supply CFM:</strong></td><td class="highlight">' . ($grand_total_supply_cfm ? number_format($grand_total_supply_cfm, 2) : 'N/A') . '</td></tr>
        <tr><td><strong>Calculated ACPH:</strong></td><td class="highlight">' . ($calculated_acph ? number_format($calculated_acph, 2) : 'N/A') . '</td></tr>
    </table>';
    
    // Add filter data if available
    if (!empty($filters)) {
        $certificate_html .= '<pagebreak /><h2>Test Results</h2>
        <table class="results-table">
            <thead>
                <tr>
                    <th>Room Name</th>
                    <th>Area Classification</th>
                    <th>Instrument ID</th>
                    <th>Filter Code</th>
                    <th>Avg in fpm</th>
                    <th>Cell Area (AC)</th>
                    <th>Flow Rate</th>
                    <th>Total Flow Rate</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($filters as $filter_id => $filter) {
            $certificate_html .= '<tr>
                <td>' . htmlspecialchars($room_name) . '</td>
                <td>' . htmlspecialchars($area_classification) . '</td>
                <td>' . getInstrumentSummaryForFilter($filter, $instruments_lookup) . '</td>
                <td>' . htmlspecialchars($filter['filter_code'] ?? 'N/A') . '</td>
                <td class="highlight">' . number_format($filter['average_reading'] ?? 0, 2) . '</td>
                <td>' . number_format($filter['cell_area'] ?? 0, 2) . '</td>
                <td>' . number_format($filter['flow_rate'] ?? 0, 2) . '</td>
                <td class="highlight">' . number_format($filter['total_flow_rate'] ?? 0, 2) . '</td>
            </tr>';
        }
        
        // Add Total Flow Rate Sum row
        $certificate_html .= '<tr class="summary-row">
                <td colspan="7" style="text-align: right; font-weight: bold;">Total Flow Rate Sum:</td>
                <td style="font-weight: bold; background-color: #ffffcc;">' . ($total_flow_rate_sum ? number_format($total_flow_rate_sum, 2) : '0.00') . '</td>
            </tr>';
            
        $certificate_html .= '</tbody></table>';
    } else {
        $certificate_html .= '<h2>Test Certificate</h2><p>No filter data available for this test.</p>';
    }
    
    // Add Test Performance and Witness Details section (UNCHANGED)
    if ($witnessData) {
        $test_done_by = htmlspecialchars($witnessData['finalised_by_name'] ?? 'N/A');
        $test_done_on = 'N/A';
        if (!empty($witnessData['test_finalised_on'])) {
            $test_done_on = date('d.m.Y H:i', strtotime($witnessData['test_finalised_on']));
        }
        
        $test_witnessed_by = htmlspecialchars($witnessData['witness_name'] ?? 'N/A');
        $test_witnessed_on = 'N/A';
        if (!empty($witnessData['test_witnessed_on'])) {
            $test_witnessed_on = date('d.m.Y H:i', strtotime($witnessData['test_witnessed_on']));
        }
        
        $certificate_html .= '
    <h2>Test Performance and Witness Details</h2>
    <table>
        <tr><td><strong>Test Done By:</strong></td><td>' . $test_done_by . '</td></tr>
        <tr><td><strong>Test Done On:</strong></td><td>' . $test_done_on . '</td></tr>
        <tr><td><strong>Test Witnessed By:</strong></td><td>' . $test_witnessed_by . '</td></tr>
        <tr><td><strong>Test Witnessed On:</strong></td><td>' . $test_witnessed_on . '</td></tr>
    </table>';
    }
    
    // ADD NEW SEPARATE "Test Approval Details" SECTION with identical design
    if ($qaApprovalDetails) {
        $certificate_html .= '
    <h2>Test Approval Details</h2>
    <table>
        <tr><td><strong>Test Approved By:</strong></td><td>' . htmlspecialchars($qaApprovalDetails['approved_by']) . '</td></tr>
        <tr><td><strong>Test Approved On:</strong></td><td>' . date('d.m.Y H:i:s', strtotime($qaApprovalDetails['approved_on'])) . '</td></tr>
    </table>';
    }
    
    $certificate_html .= '
</body>
</html>';

        // Create certificate PDF with landscape orientation
        $certificate_mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 30,
            'margin_bottom' => 25,
            'margin_header' => 10,
            'margin_footer' => 10
        ]);
        
        $certi_header_html = '<table width="100%" style="border: none; border-collapse: collapse;">
        <tr>
            <td width="70%" style="text-align: left; vertical-align: top; border: none; padding: 0;">
                <h1 style="color: #333; font-size: 16pt; margin: 0;">Test Certificate</h1>
            </td>
            <td width="30%" style="text-align: right; vertical-align: middle; border: none; padding: 0;">
                ' . $logo_html . '
            </td>
        </tr>
    </table>';

        $footer_html = '<table width="100%" style="font-size: 9pt; color: #666; border: none; border-collapse: collapse; margin-top: 10px;">
        <tr>
            <td width="70%" style="text-align: left; border: none; padding: 0;">
                <strong>Generated On:</strong> ' . date('d.m.Y H:i:s') . ' | 
                <strong>Requested By:</strong> ' . htmlspecialchars(($_SESSION['user_name'] ?? 'System')) . '
            </td>
            <td width="30%" style="text-align: right; border: none; padding: 0;">
                <strong>Page {PAGENO} of {nbpg}</strong>
            </td>
        </tr>
    </table>';

        $certificate_mpdf->SetHTMLHeader($certi_header_html);
        $certificate_mpdf->SetHTMLFooter($footer_html);
        
        // Write the certificate HTML to PDF
        $certificate_mpdf->WriteHTML($certificate_html);
        
        // Save certificate PDF to file
        $certificate_pdf_string = $certificate_mpdf->Output('', 'S');
        file_put_contents($file_path, $certificate_pdf_string);
        
        // Log success
        error_log("ACPH Test Certificate PDF with QA Approval generated successfully: {$filename}");
        
        return [
            'success' => true,
            'filename' => $filename,
            'file_path' => $file_path,
            'upload_path' => "../../uploads/{$filename}"
        ];
        
    } catch (Exception $e) {
        error_log("ACPH Test Certificate PDF with QA Approval Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>