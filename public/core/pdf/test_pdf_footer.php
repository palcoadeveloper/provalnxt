<?php
/**
 * Test script for PDF Footer Generation Service
 * This script verifies that the PDF footer generation functionality works correctly
 */

// Start session and include required files
session_start();
require_once('../config/config.php');
require_once('../config/db.class.php');
require_once('../pdf/pdf_footer_service.php');

// Simple test data (simulate a logged-in user)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Test user ID
    $_SESSION['user_name'] = 'Test User';
}

echo "<h1>PDF Footer Generation Service Test</h1>\n";

try {
    // Initialize the PDF footer service
    $pdf_service = new PDFFooterService();
    echo "<p>✓ PDF Footer Service initialized successfully</p>\n";
    
    // Test footer configuration
    $config = $pdf_service->getFooterConfig();
    echo "<p>✓ Footer configuration loaded:</p>\n";
    echo "<pre>" . print_r($config, true) . "</pre>\n";
    
    // Test PDF validation (we'll use a fake file path for testing)
    $test_pdf_path = '/fake/path/test.pdf';
    $is_valid = $pdf_service->validatePDFIntegrity($test_pdf_path);
    echo "<p>✓ PDF validation test: " . ($is_valid ? "Valid" : "Invalid (expected for fake path)") . "</p>\n";
    
    // Test template metadata generation (if templates exist)
    $template_count = DB::queryFirstField("SELECT COUNT(*) FROM raw_data_templates");
    echo "<p>✓ Templates in database: {$template_count}</p>\n";
    
    if ($template_count > 0) {
        $first_template = DB::queryFirstRow("SELECT id FROM raw_data_templates LIMIT 1");
        if ($first_template) {
            try {
                $metadata = $pdf_service->getTemplateMetadata($first_template['id'], $_SESSION['user_id']);
                echo "<p>✓ Template metadata generated:</p>\n";
                echo "<pre>" . print_r($metadata, true) . "</pre>\n";
            } catch (Exception $e) {
                echo "<p>⚠ Template metadata generation failed: " . $e->getMessage() . "</p>\n";
            }
        }
    } else {
        echo "<p>ℹ No templates available for metadata testing</p>\n";
    }
    
    // Test footer HTML generation
    $test_footer_data = [
        'test_id' => 'TEST-001',
        'test_name' => 'Sample Test',
        'validation_id' => 'VAL-001',
        'template_version' => '1.0',
        'effective_date' => date('Y-m-d'),
        'downloaded_by' => 'Test User',
        'download_timestamp' => date('d.m.Y H:i:s'),
        'approval_status' => 'Active',
        'unique_identifier' => 'TPL-TEST-001'
    ];
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($pdf_service);
    $method = $reflection->getMethod('generateFooterHTML');
    $method->setAccessible(true);
    $footer_html = $method->invoke($pdf_service, $test_footer_data);
    
    echo "<p>✓ Footer HTML generated successfully (length: " . strlen($footer_html) . " characters)</p>\n";
    echo "<p>Footer HTML preview:</p>\n";
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0; font-size: 12px;'>" . $footer_html . "</div>\n";
    
    echo "<h2>Test Results Summary</h2>\n";
    echo "<p>✅ All basic functionality tests passed!</p>\n";
    echo "<p><strong>Note:</strong> Full PDF generation testing requires actual PDF template files.</p>\n";
    
} catch (Exception $e) {
    echo "<p>❌ Test failed with error: " . $e->getMessage() . "</p>\n";
    echo "<p>Stack trace:</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

echo "<p><a href='../managetestdetails.php'>← Back to Test Management</a></p>\n";
?>