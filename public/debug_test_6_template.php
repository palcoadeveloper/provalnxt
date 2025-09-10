<?php
/**
 * Debug Script for Test ID 6 Template Issue
 * 
 * This script investigates why template download fails for test_id 6
 */

// Security template - mandatory for all PHP files
require_once(__DIR__ . '/core/config/config.php');
require_once(__DIR__ . '/core/config/db.class.php');

// Skip session validation for debugging
if (!isset($_SESSION)) {
    session_start();
    // Set mock session data for debugging
    $_SESSION['logged_in_user'] = 'employee';
    $_SESSION['department_id'] = 1;
    $_SESSION['user_id'] = 1;
}

// Timezone setting for audit logs
date_default_timezone_set("Asia/Kolkata");

echo "<h2>Debug Analysis: Test ID 6 Template Issue</h2>";
echo "<hr>";

// 1. Check if test_id 6 exists in tests table
echo "<h3>1. Test ID 6 Validation</h3>";
$test_exists = DB::queryFirstRow("SELECT test_id, test_name FROM tests WHERE test_id = %d", 6);
if ($test_exists) {
    echo "<p>✅ Test ID 6 exists: " . htmlspecialchars($test_exists['test_name']) . "</p>";
} else {
    echo "<p>❌ Test ID 6 does NOT exist in tests table</p>";
}

// 2. Check all templates for test_id 6
echo "<h3>2. Template Records for Test ID 6</h3>";
$all_templates = DB::query("
    SELECT rt.*, u.user_name as uploaded_by_name
    FROM raw_data_templates rt 
    LEFT JOIN users u ON rt.created_by = u.user_id 
    WHERE rt.test_id = %d 
    ORDER BY rt.created_at DESC
", 6);

if (!empty($all_templates)) {
    echo "<p>Found " . count($all_templates) . " template record(s) for test_id 6:</p>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ID</th><th>File Path</th><th>Is Active</th><th>Effective Date</th><th>Effective Till</th><th>Uploaded By</th><th>Created At</th><th>File Exists</th></tr>";
    
    foreach ($all_templates as $template) {
        $file_exists = file_exists($template['file_path']) ? '✅ Yes' : '❌ No';
        $is_active = $template['is_active'] ? '✅ Active' : '❌ Inactive';
        
        echo "<tr>";
        echo "<td>" . $template['id'] . "</td>";
        echo "<td>" . htmlspecialchars($template['file_path']) . "</td>";
        echo "<td>" . $is_active . "</td>";
        echo "<td>" . ($template['effective_date'] ?? 'NULL') . "</td>";
        echo "<td>" . ($template['effective_till_date'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($template['uploaded_by_name'] ?? 'Unknown') . "</td>";
        echo "<td>" . $template['created_at'] . "</td>";
        echo "<td>" . $file_exists . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No template records found for test_id 6</p>";
}

// 3. Check active template specifically
echo "<h3>3. Active Template Check</h3>";
$active_template = DB::queryFirstRow("
    SELECT rt.*, t.test_name 
    FROM raw_data_templates rt 
    LEFT JOIN tests t ON rt.test_id = t.test_id 
    WHERE rt.test_id = %d AND rt.is_active = 1
", 6);

if ($active_template) {
    echo "<p>✅ Active template found:</p>";
    echo "<ul>";
    echo "<li><strong>Template ID:</strong> " . $active_template['id'] . "</li>";
    echo "<li><strong>File Path:</strong> " . htmlspecialchars($active_template['file_path']) . "</li>";
    echo "<li><strong>Effective Date:</strong> " . ($active_template['effective_date'] ?? 'NULL') . "</li>";
    echo "<li><strong>File Exists:</strong> " . (file_exists($active_template['file_path']) ? '✅ Yes' : '❌ No') . "</li>";
    echo "</ul>";
    
    // Test the download URL
    $download_url = "core/pdf/template_handler.php?action=download&id=" . $active_template['id'];
    echo "<p><strong>Expected Download URL:</strong> <a href='" . $download_url . "' target='_blank'>" . $download_url . "</a></p>";
} else {
    echo "<p>❌ No active template found for test_id 6</p>";
}

// 4. Check template directory
echo "<h3>4. Template Directory Check</h3>";
$uploads_dir = realpath(__DIR__ . '/uploads/');
$templates_dir = $uploads_dir . '/templates/';

echo "<p><strong>Uploads Directory:</strong> " . $uploads_dir . " (" . (is_dir($uploads_dir) ? '✅ Exists' : '❌ Missing') . ")</p>";
echo "<p><strong>Templates Directory:</strong> " . $templates_dir . " (" . (is_dir($templates_dir) ? '✅ Exists' : '❌ Missing') . ")</p>";

if (is_dir($templates_dir)) {
    $template_files = glob($templates_dir . 'test_6_*');
    echo "<p><strong>Test 6 Template Files:</strong></p>";
    if (!empty($template_files)) {
        echo "<ul>";
        foreach ($template_files as $file) {
            echo "<li>" . basename($file) . " (Size: " . filesize($file) . " bytes)</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ No template files found matching 'test_6_*' pattern</p>";
    }
}

// 5. Simulate getuploadedfiles.php logic
echo "<h3>5. Template Display Logic Test</h3>";
$test_wf_id = "T-1-7-6-1757189557"; // From the URL
$val_wf_id = "V-1-7-1744396200-Y"; // From the URL

// Get test_id from workflow (like getuploadedfiles.php does)
$derived_test_id = DB::queryFirstField("SELECT test_id FROM tbl_test_schedules_tracking WHERE test_wf_id = %s", $test_wf_id);
echo "<p><strong>Test WF ID:</strong> " . $test_wf_id . "</p>";
echo "<p><strong>Derived Test ID:</strong> " . ($derived_test_id ?? 'NULL') . "</p>";

if ($derived_test_id != 6) {
    echo "<p>⚠️ <strong>MISMATCH:</strong> Derived test_id (" . ($derived_test_id ?? 'NULL') . ") doesn't match expected test_id 6</p>";
}

// Check if template should be shown based on user role
$user_role = $_SESSION['logged_in_user'] ?? 'unknown';
$dept_id = $_SESSION['department_id'] ?? 'unknown';
echo "<p><strong>User Role:</strong> " . $user_role . ", <strong>Department ID:</strong> " . $dept_id . "</p>";

$should_show_template = (($user_role == "vendor") || ($user_role == "employee" && $dept_id == 1));
echo "<p><strong>Should Show Template:</strong> " . ($should_show_template ? '✅ Yes' : '❌ No') . "</p>";

// 6. Test template handler directly
echo "<h3>6. Template Handler Test</h3>";
if ($active_template) {
    $template_id = $active_template['id'];
    echo "<p>Testing template handler for template ID: " . $template_id . "</p>";
    
    // Simulate the handler check
    $handler_test = DB::queryFirstRow("
        SELECT rt.*, t.test_name 
        FROM raw_data_templates rt 
        LEFT JOIN tests t ON rt.test_id = t.test_id 
        WHERE rt.id = %d", $template_id);
    
    if ($handler_test) {
        echo "<p>✅ Template handler would find the template</p>";
        if (file_exists($handler_test['file_path'])) {
            echo "<p>✅ Template file exists at: " . htmlspecialchars($handler_test['file_path']) . "</p>";
        } else {
            echo "<p>❌ Template file MISSING at: " . htmlspecialchars($handler_test['file_path']) . "</p>";
        }
    } else {
        echo "<p>❌ Template handler would NOT find the template</p>";
    }
} else {
    echo "<p>⚠️ Cannot test template handler - no active template found</p>";
}

echo "<hr>";
echo "<h3>Summary & Recommendations</h3>";

if (!$test_exists) {
    echo "<p>🔴 <strong>CRITICAL:</strong> Test ID 6 doesn't exist in the tests table. This needs to be created first.</p>";
} elseif (empty($all_templates)) {
    echo "<p>🟡 <strong>ISSUE:</strong> No template records exist for test_id 6. A template needs to be uploaded.</p>";
} elseif (!$active_template) {
    echo "<p>🟡 <strong>ISSUE:</strong> Template records exist but none are active. Need to activate a template.</p>";
} elseif (!file_exists($active_template['file_path'])) {
    echo "<p>🔴 <strong>CRITICAL:</strong> Active template record exists but file is missing from filesystem.</p>";
} else {
    echo "<p>🟢 <strong>GOOD:</strong> Active template exists and file is present. Issue might be elsewhere.</p>";
}

if ($derived_test_id && $derived_test_id != 6) {
    echo "<p>🔴 <strong>DATA INCONSISTENCY:</strong> Test workflow maps to test_id " . $derived_test_id . " instead of 6.</p>";
}

?>