<!DOCTYPE html>
<html>
<head>
    <title>Upload Consolidation Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { border: 1px solid #ccc; padding: 15px; margin: 10px 0; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .small { font-size: 12px; }
    </style>
</head>
<body>
    <h2>Upload Consolidation Test</h2>
    <p>This page tests the consolidated upload functionality and instrument calibration certificate integration.</p>
    
    <?php
    require_once('./core/config/config.php');
    require_once('./core/config/db.class.php');
    
    echo "<div class='test-section'>";
    echo "<h3>Recent Upload Records Analysis</h3>";
    
    // Get recent upload records to analyze the new structure
    $recentUploads = DB::query("
        SELECT 
            test_wf_id,
            upload_type,
            upload_path_raw_data,
            upload_path_test_certificate,
            upload_path_master_certificate,
            upload_path_other_doc,
            uploaded_datetime,
            uploaded_by
        FROM tbl_uploads 
        WHERE uploaded_datetime >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ORDER BY uploaded_datetime DESC 
        LIMIT 20
    ");
    
    if (!empty($recentUploads)) {
        echo "<table>";
        echo "<tr>";
        echo "<th>Test WF ID</th>";
        echo "<th>Upload Type</th>";
        echo "<th>Raw Data</th>";
        echo "<th>Test Cert</th>";
        echo "<th>Master Cert</th>";
        echo "<th>Other Doc</th>";
        echo "<th>Upload Time</th>";
        echo "</tr>";
        
        foreach ($recentUploads as $upload) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($upload['test_wf_id']) . "</td>";
            echo "<td class='small'>" . htmlspecialchars($upload['upload_type']) . "</td>";
            echo "<td>" . (!empty($upload['upload_path_raw_data']) ? '✅' : '❌') . "</td>";
            echo "<td>" . (!empty($upload['upload_path_test_certificate']) ? '✅' : '❌') . "</td>";
            echo "<td>" . (!empty($upload['upload_path_master_certificate']) ? '✅' : '❌') . "</td>";
            echo "<td>" . (!empty($upload['upload_path_other_doc']) ? '✅' : '❌') . "</td>";
            echo "<td class='small'>" . htmlspecialchars($upload['uploaded_datetime']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='info'>No recent upload records found.</p>";
    }
    
    echo "</div>";
    
    // Check for consolidated records
    echo "<div class='test-section'>";
    echo "<h3>Consolidated Upload Records</h3>";
    
    $consolidatedUploads = DB::query("
        SELECT 
            test_wf_id,
            upload_path_raw_data,
            upload_path_test_certificate,
            uploaded_datetime,
            uploaded_by
        FROM tbl_uploads 
        WHERE upload_type = 'acph_test_documents'
        ORDER BY uploaded_datetime DESC 
        LIMIT 10
    ");
    
    if (!empty($consolidatedUploads)) {
        echo "<p class='success'>Found " . count($consolidatedUploads) . " consolidated upload records!</p>";
        echo "<table>";
        echo "<tr>";
        echo "<th>Test WF ID</th>";
        echo "<th>Raw Data Path</th>";
        echo "<th>Test Certificate Path</th>";
        echo "<th>Upload Time</th>";
        echo "</tr>";
        
        foreach ($consolidatedUploads as $upload) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($upload['test_wf_id']) . "</td>";
            echo "<td class='small'>" . htmlspecialchars(basename($upload['upload_path_raw_data'] ?? '')) . "</td>";
            echo "<td class='small'>" . htmlspecialchars(basename($upload['upload_path_test_certificate'] ?? '')) . "</td>";
            echo "<td class='small'>" . htmlspecialchars($upload['uploaded_datetime']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='info'>No consolidated upload records found yet. Try finalizing a test with Paper on Glass enabled.</p>";
    }
    
    echo "</div>";
    
    // Check for instrument calibration certificate records
    echo "<div class='test-section'>";
    echo "<h3>Instrument Calibration Certificate Records</h3>";
    
    $certUploads = DB::query("
        SELECT 
            test_wf_id,
            upload_path_master_certificate,
            uploaded_datetime
        FROM tbl_uploads 
        WHERE upload_type = 'instrument_calibration_certificate'
        ORDER BY uploaded_datetime DESC 
        LIMIT 15
    ");
    
    if (!empty($certUploads)) {
        echo "<p class='success'>Found " . count($certUploads) . " instrument calibration certificate records!</p>";
        echo "<table>";
        echo "<tr>";
        echo "<th>Test WF ID</th>";
        echo "<th>Certificate Path</th>";
        echo "<th>Upload Time</th>";
        echo "</tr>";
        
        foreach ($certUploads as $cert) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($cert['test_wf_id']) . "</td>";
            echo "<td class='small'>" . htmlspecialchars(basename($cert['upload_path_master_certificate'] ?? '')) . "</td>";
            echo "<td class='small'>" . htmlspecialchars($cert['uploaded_datetime']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='info'>No instrument calibration certificate records found yet.</p>";
    }
    
    echo "</div>";
    
    // Test function availability
    echo "<div class='test-section'>";
    echo "<h3>Function Availability Test</h3>";
    
    if (function_exists('uploadInstrumentCalibrationCertificates')) {
        echo "<p class='success'>✅ uploadInstrumentCalibrationCertificates function is available</p>";
    } else {
        echo "<p class='error'>❌ uploadInstrumentCalibrationCertificates function is NOT available</p>";
    }
    
    echo "</div>";
    
    // Show summary of expected behavior
    echo "<div class='test-section'>";
    echo "<h3>Expected Behavior Summary</h3>";
    echo "<p><strong>🎯 After Test Finalization:</strong></p>";
    echo "<ul>";
    echo "<li><strong>1 Record</strong>: upload_type = 'acph_test_documents' (contains both Raw Data and Test Certificate PDFs)</li>";
    echo "<li><strong>N Records</strong>: upload_type = 'instrument_calibration_certificate' (one for each tagged instrument)</li>";
    echo "</ul>";
    
    echo "<p><strong>🔧 To Test:</strong></p>";
    echo "<ol>";
    echo "<li>Go to a test with Paper on Glass enabled and Data Entry Mode = Online</li>";
    echo "<li>Tag some instruments with the test</li>";
    echo "<li>Finalize the test data</li>";
    echo "<li>Check this page to see the consolidated records</li>";
    echo "</ol>";
    echo "</div>";
    ?>
    
</body>
</html>