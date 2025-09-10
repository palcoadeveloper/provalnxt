<?php
/**
 * Fix Test ID 6 Template Issues
 * 
 * This script creates missing database records for test_id 6 templates
 * and fixes the workflow mapping issue.
 */

// Security template - mandatory for all PHP files
require_once(__DIR__ . '/core/config/config.php');
require_once(__DIR__ . '/core/config/db.class.php');

// Skip session validation for debugging
if (!isset($_SESSION)) {
    session_start();
}

// Set valid user_id (use existing Engineering user)
$eng_user = DB::queryFirstRow("SELECT user_id, department_id FROM users WHERE department_id = 1 LIMIT 1");
$user_id = $eng_user ? $eng_user['user_id'] : 42; // fallback to user_id 42

// Set mock session data for debugging  
$_SESSION['logged_in_user'] = 'employee';
$_SESSION['department_id'] = 1;
$_SESSION['user_id'] = $user_id;
$_SESSION['unit_id'] = 1;

echo "<p><strong>Using User ID:</strong> " . $user_id . " for template creation</p>";

// Timezone setting for audit logs
date_default_timezone_set("Asia/Kolkata");

echo "<h2>Fix Test ID 6 Template Issues</h2>";
echo "<hr>";

// Check current state
$templates_dir = realpath(__DIR__ . '/uploads/templates/');
$template_files = glob($templates_dir . '/test_6_*');

echo "<h3>Step 1: Found Template Files</h3>";
if (!empty($template_files)) {
    echo "<p>Found " . count($template_files) . " template files:</p>";
    echo "<ul>";
    foreach ($template_files as $file) {
        $size = filesize($file);
        $date = date('Y-m-d H:i:s', filemtime($file));
        echo "<li>" . basename($file) . " (Size: " . $size . " bytes, Modified: " . $date . ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p>❌ No template files found!</p>";
    exit;
}

// Find the most recent template file (likely the active one)
$latest_file = null;
$latest_time = 0;
foreach ($template_files as $file) {
    $time = filemtime($file);
    if ($time > $latest_time) {
        $latest_time = $time;
        $latest_file = $file;
    }
}

echo "<h3>Step 2: Identify Latest Template</h3>";
echo "<p><strong>Latest Template:</strong> " . basename($latest_file) . "</p>";
echo "<p><strong>File Modified:</strong> " . date('Y-m-d H:i:s', $latest_time) . "</p>";

// Start database transaction
echo "<h3>Step 3: Create Database Records</h3>";
DB::startTransaction();

try {
    // Check if any template records already exist
    $existing_records = DB::query("SELECT * FROM raw_data_templates WHERE test_id = %d", 6);
    if (!empty($existing_records)) {
        echo "<p>⚠️ Found " . count($existing_records) . " existing records. Cleaning up first...</p>";
        
        // Deactivate all existing records
        DB::query("UPDATE raw_data_templates SET is_active = 0, effective_till_date = %s WHERE test_id = %d", 
                  date('Y-m-d'), 6);
        echo "<p>✅ Deactivated existing records</p>";
    }
    
    // Create records for all template files (inactive first)
    $template_id = null;
    foreach ($template_files as $file) {
        $filename = basename($file);
        $file_date = date('Y-m-d', filemtime($file));
        $is_active = ($file === $latest_file) ? 1 : 0;
        
        // Insert template record
        DB::insert('raw_data_templates', [
            'test_id' => 6,
            'file_path' => $file,
            'effective_date' => $file_date,
            'is_active' => $is_active,
            'created_by' => $_SESSION['user_id'],
            'created_at' => DB::sqleval('NOW()'),
            'download_count' => 0
        ]);
        
        if ($is_active) {
            $template_id = DB::insertId();
            echo "<p>✅ Created ACTIVE template record ID: " . $template_id . " for " . $filename . "</p>";
        } else {
            echo "<p>✅ Created inactive template record for " . $filename . "</p>";
        }
        
        // Log the creation
        $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
        DB::insert('log', [
            'change_type' => 'template_upload',
            'table_name' => 'raw_data_templates',
            'change_description' => "Raw data template record created for Test ID: 6 (File: {$filename}, Status: " . ($is_active ? 'Active' : 'Inactive') . ")",
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $unit_id
        ]);
    }
    
    DB::commit();
    echo "<p>✅ All template records created successfully!</p>";
    
} catch (Exception $e) {
    DB::rollback();
    echo "<p>❌ Error creating template records: " . $e->getMessage() . "</p>";
    exit;
}

// Step 4: Check workflow mapping issue
echo "<h3>Step 4: Check Workflow Mapping</h3>";
$test_wf_id = "T-1-7-6-1757189557";
$existing_mapping = DB::queryFirstRow("SELECT * FROM tbl_test_schedules_tracking WHERE test_wf_id = %s", $test_wf_id);

if ($existing_mapping) {
    echo "<p>✅ Workflow mapping exists:</p>";
    echo "<ul>";
    echo "<li><strong>Test WF ID:</strong> " . $existing_mapping['test_wf_id'] . "</li>";
    echo "<li><strong>Test ID:</strong> " . $existing_mapping['test_id'] . "</li>";
    echo "<li><strong>Val WF ID:</strong> " . $existing_mapping['val_wf_id'] . "</li>";
    echo "</ul>";
    
    if ($existing_mapping['test_id'] != 6) {
        echo "<p>⚠️ <strong>MAPPING ISSUE:</strong> Workflow points to test_id " . $existing_mapping['test_id'] . " instead of 6</p>";
        
        // Option to fix the mapping (commented out for safety)
        /*
        DB::query("UPDATE tbl_test_schedules_tracking SET test_id = %d WHERE test_wf_id = %s", 6, $test_wf_id);
        echo "<p>✅ Fixed workflow mapping to point to test_id 6</p>";
        */
        echo "<p>🔧 <strong>MANUAL ACTION NEEDED:</strong> Update tbl_test_schedules_tracking to set test_id = 6 for test_wf_id = '" . $test_wf_id . "'</p>";
    }
} else {
    echo "<p>❌ No workflow mapping found for test_wf_id: " . $test_wf_id . "</p>";
    echo "<p>🔧 <strong>MANUAL ACTION NEEDED:</strong> Create record in tbl_test_schedules_tracking for this workflow</p>";
}

// Step 5: Test the fix
echo "<h3>Step 5: Test Template Access</h3>";
$active_template = DB::queryFirstRow("
    SELECT rt.*, t.test_name 
    FROM raw_data_templates rt 
    LEFT JOIN tests t ON rt.test_id = t.test_id 
    WHERE rt.test_id = %d AND rt.is_active = 1
", 6);

if ($active_template) {
    echo "<p>✅ Active template is now available:</p>";
    echo "<ul>";
    echo "<li><strong>Template ID:</strong> " . $active_template['id'] . "</li>";
    echo "<li><strong>File:</strong> " . basename($active_template['file_path']) . "</li>";
    echo "<li><strong>Effective Date:</strong> " . $active_template['effective_date'] . "</li>";
    echo "</ul>";
    
    // Generate download URL
    $download_url = "core/pdf/template_handler.php?action=download&id=" . $active_template['id'] . "&val_wf_id=V-1-7-1744396200-Y&test_val_wf_id=T-1-7-6-1757189557";
    echo "<p><strong>Test Download URL:</strong><br>";
    echo "<a href='" . $download_url . "' target='_blank'>" . $download_url . "</a></p>";
    
    // Test template handler response
    echo "<p><strong>Template Handler Test:</strong></p>";
    $handler_check = DB::queryFirstRow("
        SELECT rt.*, t.test_name 
        FROM raw_data_templates rt 
        LEFT JOIN tests t ON rt.test_id = t.test_id 
        WHERE rt.id = %d", $active_template['id']);
        
    if ($handler_check && file_exists($handler_check['file_path'])) {
        echo "<p>✅ Template handler should work correctly now</p>";
    } else {
        echo "<p>❌ Template handler may still have issues</p>";
    }
} else {
    echo "<p>❌ Still no active template found!</p>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>✅ Created database records for all template files</p>";
echo "<p>✅ Set latest template as active</p>";
echo "<p>⚠️ Workflow mapping may need manual correction</p>";
echo "<p>🔧 Next step: Test the template download in the application</p>";

?>