<?php
require_once('./core/config/config.php');

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('core/security/session_validation.php');
validateUserSession();

require_once("core/config/db.class.php");

// Get test workflow ID from URL parameter
$test_wf_id = $_GET['test_wf_id'] ?? 'T-1-7-1-1756793223';
$frontend_test_date = $_GET['test_conducted_date'] ?? $_POST['test_conducted_date'] ?? '';

// Validate test workflow ID format
if (!preg_match('/^[A-Z0-9\-]+$/', $test_wf_id)) {
    die("Invalid test workflow ID format");
}

try {
    // Get test and workflow information
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
    
    if (!$test_info) {
        throw new Exception("Test information not found for workflow ID: $test_wf_id");
    }
    
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
    $user_unit_id = getUserUnitId();
    
    $filter_data_records = [];
    if (isVendor()) {
        $filter_query = "
            SELECT tsd.data_json, tsd.section_type, tsd.entered_date, tsd.modified_date, tsd.filter_id,
                   u1.user_name as entered_by_name, u2.user_name as modified_by_name
            FROM test_specific_data tsd
            LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
            LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
            WHERE tsd.test_val_wf_id = %s 
            AND tsd.section_type LIKE 'acph_filter_%'
            AND tsd.status = 'Active'
            ORDER BY tsd.section_type
        ";
        $filter_data_records = DB::query($filter_query, $test_wf_id);
    } else {
        $filter_query = "
            SELECT tsd.data_json, tsd.section_type, tsd.entered_date, tsd.modified_date, tsd.filter_id,
                   u1.user_name as entered_by_name, u2.user_name as modified_by_name
            FROM test_specific_data tsd
            LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
            LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
            WHERE tsd.test_val_wf_id = %s 
            AND tsd.section_type LIKE 'acph_filter_%'
            AND tsd.status = 'Active'
            AND tsd.unit_id = %i
            ORDER BY tsd.section_type
        ";
        $filter_data_records = DB::query($filter_query, $test_wf_id, intval($user_unit_id));
    }
    
    // Get instrument details for this test
    $instrument_info = DB::queryFirstRow("
        SELECT 
            i.instrument_type,
            i.instrument_id,
            i.serial_number,
            i.calibrated_on,
            i.calibration_due_on,
            i.instrument_status
        FROM test_instruments ti
        JOIN instruments i ON ti.instrument_id = i.instrument_id
        WHERE ti.test_val_wf_id = %s
        AND ti.is_active = '1'
        LIMIT 1
    ", $test_wf_id);
    
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
        $reading_sum = 0;
        $reading_count = 0;
        
        // Check if readings are stored in the new nested format
        if (isset($filter_data['readings']) && is_array($filter_data['readings'])) {
            // New format: readings: { "reading_1": {"value": "5", "instrument_id": "INs234"}, ... }
            for ($i = 1; $i <= 5; $i++) {
                $reading_key = "reading_$i";
                if (isset($filter_data['readings'][$reading_key])) {
                    $reading_value = $filter_data['readings'][$reading_key]['value'] ?? '';
                    $readings[$reading_key] = $reading_value;
                    
                    if (is_numeric($reading_value) && $reading_value > 0) {
                        $reading_sum += floatval($reading_value);
                        $reading_count++;
                    }
                } else {
                    $readings[$reading_key] = '';
                }
            }
        } else {
            // Old format: reading_1, reading_2, etc. as direct fields
            for ($i = 1; $i <= 5; $i++) {
                $reading_value = $filter_data["reading_$i"] ?? '';
                $readings["reading_$i"] = $reading_value;
                
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
            'average_reading' => $average_reading,
            'total_flow_rate' => round($flow_rate, 2),
            'entered_by' => $record['entered_by_name'] ?? 'N/A',
            'entered_date' => $record['entered_date'] ?? null,
            'modified_by' => $record['modified_by_name'] ?? null,
            'modified_date' => $record['modified_date'] ?? null
        ];
        
        // Get global values from stored data or ERF mappings
        if ($room_volume === null) {
            $room_volume = $filter_data['room_volume'] ?? ($room_info['room_volume'] ?? null);
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
    
    // Get test finalization details from tbl_test_finalisation_details
    $finalization_details = DB::queryFirstRow("
        SELECT 
            tfd.test_finalised_by,
            tfd.test_finalised_on,
            tfd.witness,
            tfd.test_witnessed_on,
            u1.user_name as finalised_by_name,
            u2.user_name as witness_name
        FROM tbl_test_finalisation_details tfd
        LEFT JOIN users u1 ON tfd.test_finalised_by = u1.user_id
        LEFT JOIN users u2 ON tfd.witness = u2.user_id
        WHERE tfd.test_wf_id = %s 
        AND tfd.status = 'Active'
        ORDER BY tfd.test_finalised_on DESC
        LIMIT 1
    ", $test_wf_id);
    
} catch (Exception $e) {
    die("Error loading test data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Data Preview - <?php echo htmlspecialchars($test_wf_id); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        
        .container {
            max-width: 297mm; /* A4 landscape width */
            margin: 0 auto;
            background: white;
            padding: 10mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            font-size: 16pt; /* 12pt equivalent in PDF */
            font-weight: bold;
            margin: 0;
            color: #333;
        }
        
        h2 {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10pt;
        }
        
        .info-table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        
        .info-table td:first-child {
            background-color: #f8f9fa;
            font-weight: bold;
            width: 40%;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 8pt;
            table-layout: fixed;
        }
        
        .results-table th,
        .results-table td {
            padding: 3px;
            border: 1px solid #ddd;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
        }
        
        /* Redesigned column structure with proper balance */
        .results-table colgroup col:nth-child(1) { width: 14%; } /* Room Name */
        .results-table colgroup col:nth-child(2) { width: 6%; }  /* Area Classification */
        .results-table colgroup col:nth-child(3) { width: 6%; }  /* Instrument ID */
        .results-table colgroup col:nth-child(4) { width: 12%; }  /* Filter Code */
        .results-table colgroup col:nth-child(5) { width: 3.9%; }  /* R1 */
        .results-table colgroup col:nth-child(6) { width: 3.9%; }  /* R2 */
        .results-table colgroup col:nth-child(7) { width: 3.9%; }  /* R3 */
        .results-table colgroup col:nth-child(8) { width: 3.9%; }  /* R4 */
        .results-table colgroup col:nth-child(9) { width: 3.9%; }  /* R5 */
        .results-table colgroup col:nth-child(10) { width: 7%; } /* Avg */
        .results-table colgroup col:nth-child(11) { width: 6%; } /* Cell Area */
        .results-table colgroup col:nth-child(12) { width: 6%; } /* Flow Rate */
        .results-table colgroup col:nth-child(13) { width: 6.5%; } /* Total Flow Rate */
        .results-table colgroup col:nth-child(14) { width: 11.5%; } /* Entered By */
        .results-table colgroup col:nth-child(15) { width: 10.5%; } /* Entry Date */
        
        .results-table th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        
        .results-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .logo-section {
            flex-shrink: 0;
        }
        
        .logo-section img {
            height: 42px;
            max-width: 105px;
            object-fit: contain;
        }
        
        .title-section {
            flex-grow: 1;
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .highlight {
            background-color: #fff3cd !important;
            font-weight: bold;
        }
        
        .footer-info {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
        
        .footer-row {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
        }
        
        @media print {
            body {
                background: white;
                margin: 0;
            }
            
            .container {
                box-shadow: none;
                margin: 0;
                padding: 15mm;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with Logo -->
        <div class="header-section">
            <div class="title-section">
                <h1>Test Raw Data</h1>
            </div>
            <div class="logo-section">
                <img src="assets/images/logo.png" alt="Cipla Logo">
            </div>
        </div>
        
        <!-- Test Details -->
        <h2>Test Details</h2>
        <table class="info-table">
            <tr>
                <td>Test Purpose:</td>
                <td><?php echo htmlspecialchars($test_info['test_purpose'] ?? 'Air Changes Per Hour Validation'); ?></td>
            </tr>
            <tr>
                <td>Test Date:</td>
                <td><?php 
                    if (!empty($frontend_test_date)) {
                        // Use frontend date (already in d.m.Y format)
                        echo htmlspecialchars($frontend_test_date);
                    } elseif ($test_info['test_conducted_date']) {
                        echo date('d/m/Y', strtotime($test_info['test_conducted_date']));
                    } elseif ($test_info['test_wf_planned_start_date']) {
                        echo date('d/m/Y', strtotime($test_info['test_wf_planned_start_date']));
                    } else {
                        echo 'N/A';
                    }
                ?></td>
            </tr>
            <tr>
                <td>Test Frequency:</td>
                <td><?php echo htmlspecialchars($test_info['test_description'] ?? 'As per SOP'); ?></td>
            </tr>
            <tr>
                <td>Test Workflow ID:</td>
                <td><?php echo htmlspecialchars($test_wf_id); ?></td>
            </tr>
            <tr>
                <td>Equipment:</td>
                <td><?php echo htmlspecialchars($test_info['equipment_code'] . ($test_info['area_served'] ? ' - ' . $test_info['area_served'] : '')); ?></td>
            </tr>
        </table>
        
        <!-- Instrument Details -->
        <h2>Instrument Details</h2>
        <table class="info-table">
            <tr>
                <td>Instrument Type:</td>
                <td><?php echo htmlspecialchars($instrument_info['instrument_type'] ?? 'Digital Anemometer'); ?></td>
            </tr>
            <tr>
                <td>Instrument ID:</td>
                <td><?php echo htmlspecialchars($instrument_info['instrument_id'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Instrument Serial Number:</td>
                <td><?php echo htmlspecialchars($instrument_info['serial_number'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Calibration Done On:</td>
                <td><?php echo $instrument_info['calibrated_on'] ? date('d/m/Y', strtotime($instrument_info['calibrated_on'])) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td>Calibration Due On:</td>
                <td><?php echo $instrument_info['calibration_due_on'] ? date('d/m/Y', strtotime($instrument_info['calibration_due_on'])) : 'N/A'; ?></td>
            </tr>
        </table>
        
        <!-- Calculations Summary -->
        <h2>Calculations Summary</h2>
        <table class="info-table">
            <tr>
                <td>Total Room Volume (m³):</td>
                <td class="highlight"><?php echo $room_volume ? number_format($room_volume, 2) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td>Grand Total Supply CFM:</td>
                <td class="highlight"><?php echo $grand_total_supply_cfm ? number_format($grand_total_supply_cfm, 2) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td>Calculated ACPH:</td>
                <td class="highlight"><?php echo $calculated_acph ? number_format($calculated_acph, 2) : 'N/A'; ?></td>
            </tr>
        </table>
        
        <!-- Test Performance and Witness Details -->
        <h2>Test Performance and Witness Details</h2>
        <table class="info-table">
            <tr>
                <td>Test Done By:</td>
                <td><?php echo htmlspecialchars($finalization_details['finalised_by_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Test Done On:</td>
                <td><?php 
                    if ($finalization_details && $finalization_details['test_finalised_on']) {
                        echo date('d.m.Y H:i', strtotime($finalization_details['test_finalised_on']));
                    } else {
                        echo 'N/A';
                    }
                ?></td>
            </tr>
            <tr>
                <td>Test Witnessed By:</td>
                <td><?php echo htmlspecialchars($finalization_details['witness_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Test Witnessed On:</td>
                <td><?php 
                    if ($finalization_details && $finalization_details['test_witnessed_on']) {
                        echo date('d.m.Y H:i', strtotime($finalization_details['test_witnessed_on']));
                    } else {
                        echo 'N/A';
                    }
                ?></td>
            </tr>
        </table>
        
        <!-- Test Results -->
        <h2>Test Results</h2>
        <table class="results-table">
            <colgroup>
                <col> <!-- Room Name -->
                <col> <!-- Area Classification -->
                <col> <!-- Filter Group -->
                <col> <!-- Filter Code -->
                <col> <!-- R1 -->
                <col> <!-- R2 -->
                <col> <!-- R3 -->
                <col> <!-- R4 -->
                <col> <!-- R5 -->
                <col> <!-- Avg -->
                <col> <!-- Cell Area -->
                <col> <!-- Flow Rate -->
                <col> <!-- Total Flow Rate -->
                <col> <!-- Entered By -->
                <col> <!-- Entry Date -->
            </colgroup>
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
            <tbody>
                <?php if (empty($filters)): ?>
                <tr>
                    <td colspan="12" style="text-align: center; padding: 20px; color: #666; font-style: italic;">
                        No ACPH filter data found for test workflow ID: <?php echo htmlspecialchars($test_wf_id); ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($filters as $filter_id => $filter): ?>
                <tr>
                    <td><?php echo htmlspecialchars($room_name); ?></td>
                    <td><?php echo htmlspecialchars($area_classification); ?></td>
                    <td><?php echo htmlspecialchars($instrument_info['instrument_id'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($filter['filter_code']); ?></td>
                    <td><?php echo htmlspecialchars($filter['readings']['reading_1'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($filter['readings']['reading_2'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($filter['readings']['reading_3'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($filter['readings']['reading_4'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($filter['readings']['reading_5'] ?: '-'); ?></td>
                    <td class="highlight"><?php echo $filter['average_reading']; ?></td>
                    <td><?php echo $filter['cell_area']; ?></td>
                    <td><?php echo $filter['flow_rate']; ?></td>
                    <td class="highlight"><?php echo $filter['total_flow_rate']; ?></td>
                    <td><?php echo htmlspecialchars($filter['entered_by']); ?></td>
                    <td><?php 
                        if ($filter['entered_date']) {
                            $date = new DateTime($filter['entered_date']);
                            echo $date->format('d.m.Y H:i');
                        } else {
                            echo '-';
                        }
                    ?></td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Summary Row -->
                <tr style="background-color: #e9ecef; font-weight: bold;">
                    <td colspan="12" class="text-right">Total Flow Rate Sum:</td>
                    <td class="highlight"><?php echo round($total_flow_rate_sum, 2); ?></td>
                    <td colspan="2"></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Footer Information -->
        <div class="footer-info">
            <div class="footer-row">
                <span><strong>Generated On:</strong> <?php echo date('d/m/Y H:i:s'); ?></span>
                <span><strong>Requested By:</strong> <?php echo htmlspecialchars(($_SESSION['user_name'] ?? 'N/A') . ' - ' . ($_SESSION['department_name'] ?? $_SESSION['unit_name'] ?? 'N/A')); ?></span>
            </div>
        </div>
    </div>
</body>
</html>