<!DOCTYPE html>
<html>
<head>
    <title>Selective PDF Regeneration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { border: 1px solid #ccc; padding: 15px; margin: 10px 0; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Selective PDF Regeneration Test</h2>
    <p>This page tests the selective PDF regeneration functionality to ensure only existing document types are regenerated.</p>
    
    <?php
    require_once('./core/config/config.php');
    require_once('./core/config/db.class.php');
    require_once('./core/data/save/regenerate_witness_pdfs.php');
    
    // Test the getExistingDocumentTypes function
    echo "<div class='test-section'>";
    echo "<h3>Testing getExistingDocumentTypes Function</h3>";
    
    // Get some test workflow IDs from the database
    $testRecords = DB::query("
        SELECT test_wf_id, upload_path_raw_data, upload_path_test_certificate, upload_path_master_certificate, upload_path_other_doc
        FROM tbl_uploads 
        WHERE test_wf_id IS NOT NULL 
        ORDER BY uploaded_datetime DESC 
        LIMIT 10
    ");
    
    if (!empty($testRecords)) {
        echo "<table>";
        echo "<tr>";
        echo "<th>Test WF ID</th>";
        echo "<th>Raw Data</th>";
        echo "<th>Test Certificate</th>";
        echo "<th>Master Certificate</th>";
        echo "<th>Other Doc</th>";
        echo "<th>Detected Types</th>";
        echo "</tr>";
        
        foreach ($testRecords as $record) {
            $detectedTypes = getExistingDocumentTypes($record['test_wf_id']);
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($record['test_wf_id']) . "</td>";
            echo "<td>" . (!empty($record['upload_path_raw_data']) ? '✅' : '❌') . "</td>";
            echo "<td>" . (!empty($record['upload_path_test_certificate']) ? '✅' : '❌') . "</td>";
            echo "<td>" . (!empty($record['upload_path_master_certificate']) ? '✅' : '❌') . "</td>";
            echo "<td>" . (!empty($record['upload_path_other_doc']) ? '✅' : '❌') . "</td>";
            echo "<td class='info'>" . implode(', ', $detectedTypes) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='error'>No upload records found for testing.</p>";
    }
    
    echo "</div>";
    
    // Test conditions checking
    echo "<div class='test-section'>";
    echo "<h3>Testing PDF Regeneration Conditions</h3>";
    
    if (!empty($testRecords)) {
        $testWfId = $testRecords[0]['test_wf_id'];
        echo "<p>Testing with test_wf_id: <strong>" . htmlspecialchars($testWfId) . "</strong></p>";
        
        $conditionsMet = shouldRegeneratePDFs($testWfId);
        echo "<p>Conditions met for regeneration: " . ($conditionsMet ? "<span class='success'>YES</span>" : "<span class='error'>NO</span>") . "</p>";
        
        // Show the actual conditions
        $conditions = DB::queryFirstRow("
            SELECT 
                ts.test_wf_current_stage,
                t.paper_on_glass_enabled,
                ts.data_entry_mode
            FROM tbl_test_schedules_tracking ts
            LEFT JOIN tests t ON t.test_id = ts.test_id
            WHERE ts.test_wf_id = %s
        ", $testWfId);
        
        if ($conditions) {
            echo "<p><strong>Current conditions:</strong></p>";
            echo "<ul>";
            echo "<li>Stage: " . htmlspecialchars($conditions['test_wf_current_stage'] ?? 'NULL') . " (need: 2)</li>";
            echo "<li>Paper on Glass: " . htmlspecialchars($conditions['paper_on_glass_enabled'] ?? 'NULL') . " (need: Yes)</li>";
            echo "<li>Data Entry Mode: " . htmlspecialchars($conditions['data_entry_mode'] ?? 'NULL') . " (need: online)</li>";
            echo "</ul>";
        }
    }
    
    echo "</div>";
    
    // Show recent regeneration logs
    echo "<div class='test-section'>";
    echo "<h3>Recent PDF Regeneration Logs</h3>";
    
    $recentLogs = DB::query("
        SELECT change_description, change_by, change_date
        FROM log 
        WHERE change_type = 'acph_pdf_regeneration_witness'
        ORDER BY change_date DESC 
        LIMIT 5
    ");
    
    if (!empty($recentLogs)) {
        echo "<table>";
        echo "<tr><th>Date</th><th>Description</th><th>User ID</th></tr>";
        foreach ($recentLogs as $log) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($log['change_date']) . "</td>";
            echo "<td>" . htmlspecialchars($log['change_description']) . "</td>";
            echo "<td>" . htmlspecialchars($log['change_by']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No recent PDF regeneration logs found.</p>";
    }
    
    echo "</div>";
    
    // Show theoretical regeneration results
    echo "<div class='test-section'>";
    echo "<h3>Theoretical Regeneration Analysis</h3>";
    echo "<p>This shows what would happen if regeneration was triggered for each record:</p>";
    
    if (!empty($testRecords)) {
        echo "<table>";
        echo "<tr>";
        echo "<th>Test WF ID</th>";
        echo "<th>Original Documents</th>";
        echo "<th>Would Regenerate</th>";
        echo "<th>Would Skip</th>";
        echo "</tr>";
        
        foreach (array_slice($testRecords, 0, 5) as $record) {
            $existingTypes = getExistingDocumentTypes($record['test_wf_id']);
            $acphTypes = array_intersect($existingTypes, ['raw_data', 'test_certificate']);
            $skippedTypes = array_diff(['raw_data', 'test_certificate'], $acphTypes);
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($record['test_wf_id']) . "</td>";
            echo "<td class='info'>" . implode(', ', $existingTypes) . "</td>";
            echo "<td class='success'>" . implode(', ', $acphTypes) . "</td>";
            echo "<td class='error'>" . implode(', ', $skippedTypes) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    echo "</div>";
    ?>
    
    <div class='test-section'>
        <h3>Implementation Summary</h3>
        <p><strong>✅ Fixed Issues:</strong></p>
        <ul>
            <li>Added <code>getExistingDocumentTypes()</code> function to detect which document types exist in upload records</li>
            <li>Modified <code>regeneratePDFsWithWitness()</code> to only regenerate PDFs for existing document types</li>
            <li>Enhanced logging to show which specific document types were regenerated</li>
            <li>Updated success/failure logic to handle selective regeneration properly</li>
        </ul>
        
        <p><strong>🎯 Expected Behavior:</strong></p>
        <ul>
            <li>If a record only has Raw Data PDF → Only Raw Data PDF gets regenerated</li>
            <li>If a record only has Test Certificate PDF → Only Test Certificate PDF gets regenerated</li>
            <li>If a record has both → Both PDFs get regenerated (preserves current behavior)</li>
            <li>If a record has neither → No regeneration occurs</li>
        </ul>
        
        <p><strong>🔍 Testing Recommendations:</strong></p>
        <ul>
            <li>Test approval of documents with only Raw Data PDF</li>
            <li>Test approval of documents with only Test Certificate PDF</li>
            <li>Test approval of documents with both PDFs</li>
            <li>Verify no unwanted PDF files are created in uploads folder</li>
            <li>Check error logs for proper selective regeneration messages</li>
        </ul>
    </div>
    
</body>
</html>