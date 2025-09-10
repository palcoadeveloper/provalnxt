<?php
/*
 * Test Data Entry - Test-Specific Sections Framework
 * 
 * This file provides the framework for including test-specific data entry sections
 * based on the test_id. Each test can have custom coded sections that appear
 * when Paper on Glass is enabled.
 * 
 * Required variables in parent scope:
 * - $result (array containing test data including test_id)
 * - $test_val_wf_id (string)
 * 
 * To add a new test-specific section:
 * 1. Create a new include file: _testdataentry_[testname].php
 * 2. Add a case for your test_id in the switch statement below
 * 3. Implement your custom HTML, CSS, and JavaScript in the include file
 */

// Ensure required variables are available
if (!isset($result) || !isset($test_val_wf_id)) {
    error_log("Test Data Entry Specific: Required variables not available");
    return;
}

// Get test_id from result data
$test_id = $result['test_id'] ?? null;

if ($test_id) {
    // Include test-specific sections based on test_id
    switch(intval($test_id)) {
        case 1:
            // ACPH Test specific sections
            $test_specific_file = __DIR__ . '/_testdataentry_acph.php';
            if (file_exists($test_specific_file)) {
                include $test_specific_file;
            }
            break;
            
        case 2:
            // Temperature Test specific sections
            $test_specific_file = __DIR__ . '/_testdataentry_temperature.php';
            if (file_exists($test_specific_file)) {
                include $test_specific_file;
            }
            break;
            
        case 3:
            // Pressure Test specific sections
            $test_specific_file = __DIR__ . '/_testdataentry_pressure.php';
            if (file_exists($test_specific_file)) {
                include $test_specific_file;
            }
            break;
            
        case 4:
            // Humidity Test specific sections
            $test_specific_file = __DIR__ . '/_testdataentry_humidity.php';
            if (file_exists($test_specific_file)) {
                include $test_specific_file;
            }
            break;
            
        case 5:
            // Particle Count Test specific sections
            $test_specific_file = __DIR__ . '/_testdataentry_particlecount.php';
            if (file_exists($test_specific_file)) {
                include $test_specific_file;
            }
            break;
            
        // Add more test cases as needed
        // case 6:
        //     include '_testdataentry_yourtest.php';
        //     break;
        
        default:
            // No additional sections for this test
            // You can add a comment or placeholder here if needed
            echo '<!-- No test-specific sections defined for test_id: ' . htmlspecialchars($test_id, ENT_QUOTES, 'UTF-8') . ' -->';
            break;
    }
} else {
    // No test_id available
    echo '<!-- No test_id available for test-specific sections -->';
}
?>