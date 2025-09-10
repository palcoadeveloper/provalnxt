<?php
/**
 * Test script for PDF regeneration with witness details
 * This script can be run manually to test the PDF regeneration functionality
 * 
 * Usage: Access this file via web browser with ?test_wf_id=YOUR_TEST_ID
 * Example: http://localhost/test_pdf_regeneration.php?test_wf_id=1756793223
 */

require_once(__DIR__ . '/core/config/config.php');
require_once(__DIR__ . '/core/config/db.class.php');

// Include the PDF regeneration functions
require_once(__DIR__ . '/core/data/save/regenerate_witness_pdfs.php');

// Simple web interface for testing
?>
<!DOCTYPE html>
<html>
<head>
    <title>PDF Regeneration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .result { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
    </style>
</head>
<body>
    <h1>PDF Regeneration Test</h1>
    
    <?php
    if (isset($_GET['test_wf_id']) && !empty($_GET['test_wf_id'])) {
        // Keep test_wf_id as string since it contains format like "T-1-7-1-1756793223"
        $testWfId = htmlspecialchars(trim($_GET['test_wf_id']), ENT_QUOTES, 'UTF-8');
        
        echo "<div class='info'>Testing PDF regeneration for Test WF ID: <strong>$testWfId</strong></div>";
        
        // Test the PDF regeneration
        $result = testPDFRegeneration($testWfId);
        
        if ($result) {
            echo "<div class='success'>✅ PDF regeneration completed successfully!</div>";
        } else {
            echo "<div class='error'>❌ PDF regeneration failed. Check error logs for details.</div>";
        }
        
        // Show some debug information
        $uploadsDir = __DIR__ . "/uploads/";
        $pdfFiles = getExistingPDFFiles($testWfId);
        
        echo "<h3>Debug Information:</h3>";
        echo "<p><strong>Uploads Directory:</strong> $uploadsDir</p>";
        echo "<p><strong>Directory Exists:</strong> " . (is_dir($uploadsDir) ? 'Yes' : 'No') . "</p>";
        echo "<div class='success'>✅ Unit constraints removed - searching by test_wf_id only</div>";
        
        // Check test details from database
        try {
            $testDetails = DB::queryFirstRow("
                SELECT ts.test_wf_current_stage, t.paper_on_glass_enabled, ts.data_entry_mode, ts.test_sch_id
                FROM tbl_test_schedules_tracking ts
                LEFT JOIN tests t ON t.test_id = ts.test_id
                WHERE ts.test_wf_id = %s
            ", $testWfId);
            
            if ($testDetails) {
                echo "<p><strong>Test Details:</strong></p>";
                echo "<ul>";
                echo "<li>Stage: " . $testDetails['test_wf_current_stage'] . "</li>";
                echo "<li>Paper on Glass: " . ($testDetails['paper_on_glass_enabled'] ?? 'NULL') . "</li>";
                echo "<li>Data Entry Mode: " . ($testDetails['data_entry_mode'] ?? 'NULL') . "</li>";
                echo "<li>Test Schedule ID: " . ($testDetails['test_sch_id'] ?? 'NULL') . "</li>";
                echo "</ul>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>Error loading test details: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        echo "<p><strong>PDF Files Found:</strong></p>";
        
        if (empty($pdfFiles)) {
            echo "<div class='error'>No PDF files found for this test ID</div>";
        } else {
            echo "<ul>";
            foreach ($pdfFiles as $type => $path) {
                $exists = file_exists($path) ? 'Yes' : 'No';
                $size = file_exists($path) ? filesize($path) . ' bytes' : 'N/A';
                echo "<li><strong>$type:</strong> " . basename($path) . " (Exists: $exists, Size: $size)</li>";
            }
            echo "</ul>";
        }
        
        // Show database upload records
        echo "<h3>Database Upload Records:</h3>";
        try {
            $uploadRecords = DB::query("
                SELECT upload_id, test_wf_id, upload_path_raw_data, upload_path_test_certificate, 
                       upload_path_master_certificate, upload_path_other_documents, created_on
                FROM tbl_uploads 
                WHERE test_wf_id = %s
                ORDER BY upload_id DESC
            ", $testWfId);
            
            if (empty($uploadRecords)) {
                echo "<div class='error'>No upload records found in database for test ID: $testWfId</div>";
            } else {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Upload ID</th><th>Raw Data</th><th>Test Cert</th><th>Master Cert</th><th>Other Docs</th><th>Created</th></tr>";
                foreach ($uploadRecords as $record) {
                    echo "<tr>";
                    echo "<td>" . $record['upload_id'] . "</td>";
                    echo "<td>" . (!empty($record['upload_path_raw_data']) ? '✅ ' . basename($record['upload_path_raw_data']) : '❌') . "</td>";
                    echo "<td>" . (!empty($record['upload_path_test_certificate']) ? '✅ ' . basename($record['upload_path_test_certificate']) : '❌') . "</td>";
                    echo "<td>" . (!empty($record['upload_path_master_certificate']) ? '✅ ' . basename($record['upload_path_master_certificate']) : '❌') . "</td>";
                    echo "<td>" . (!empty($record['upload_path_other_documents']) ? '✅ ' . basename($record['upload_path_other_documents']) : '❌') . "</td>";
                    echo "<td>" . $record['created_on'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>Error loading upload records: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // Show recent files in uploads directory
        echo "<h3>Recent Files in Uploads Directory:</h3>";
        $allFiles = glob($uploadsDir . "*.pdf");
        $filteredFiles = array_filter($allFiles, function($file) use ($testWfId) {
            return strpos(basename($file), (string)$testWfId) !== false;
        });
        
        if (empty($filteredFiles)) {
            echo "<div class='info'>No files found containing test ID: $testWfId</div>";
        } else {
            echo "<ul>";
            foreach ($filteredFiles as $file) {
                $mtime = date('Y-m-d H:i:s', filemtime($file));
                $size = filesize($file);
                $exists = file_exists($file) ? '✅' : '❌';
                echo "<li>$exists " . basename($file) . " (Modified: $mtime, Size: $size bytes)</li>";
            }
            echo "</ul>";
        }
        
    } else {
        echo "<div class='info'>Please provide a test_wf_id parameter in the URL.</div>";
        echo "<p>Example: <code>?test_wf_id=1756793223</code></p>";
        
        // Show available test IDs
        try {
            $recentTests = DB::query("
                SELECT DISTINCT test_wf_id, created_on 
                FROM tbl_test_schedules_tracking 
                WHERE test_wf_current_stage = 2 
                AND paper_on_glass_enabled = 'Yes' 
                AND data_entry_mode = 'online'
                ORDER BY created_on DESC 
                LIMIT 10
            ");
            
            if (!empty($recentTests)) {
                echo "<h3>Available Test IDs (matching conditions):</h3>";
                echo "<ul>";
                foreach ($recentTests as $test) {
                    $testId = htmlspecialchars($test['test_wf_id']);
                    $created = htmlspecialchars($test['created_on']);
                    echo "<li><a href='?test_wf_id=$testId'>$testId</a> (Created: $created)</li>";
                }
                echo "</ul>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>Error loading test IDs: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    ?>
    
    <hr>
    <p><small>This is a debugging tool. Check the PHP error logs for detailed information about the PDF regeneration process.</small></p>
    
</body>
</html>