<?php
/**
 * Fix Permissions and Apply Session Optimization
 *
 * This script fixes file permissions and applies optimized session validation
 * to all PHP files in the system.
 */

// Check if running from command line for better permissions
$isCLI = php_sapi_name() === 'cli';

echo $isCLI ? "Fix and Optimize All Files\n" : "<h1>Fix and Optimize All Files</h1>\n";
echo $isCLI ? "==============================\n" : "<style>
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; }
</style>\n";

// List of files to update
$filesToUpdate = [
    'managefiltergroups.php',
    'pendingforlevel2approval.php',
    'searchmapping.php',
    'viewtestdetails.php',
    'manageequipmentdetails.php',
    'manageinstrumentdetails.php',
    'generatertschedulereport.php',
    'managefilterdetails.php',
    'preview_raw_data.php',
    'searchuser.php',
    'generateplannedvsactualrpt.php',
    'updatetaskdetails_clean.php',
    'searchtests.php',
    'addroutinetest.php',
    'managemappingdetails.php',
    'viewprotocol.php',
    'pendingforlevel1approval.php',
    'searchrtreport.php',
    'searchfilters.php',
    'managevendordetails.php',
    'viewtestdetails_modal.php',
    'searchdepartments.php',
    'viewprotocol_modal.php',
    'generateschedulereport.php',
    'pendingforlevel3approval.php',
    'searchschedule.php',
    'viewtestwindow.php',
    'searchunits.php',
    'searchreport.php',
    'generateschedule.php',
    'searchequipments.php',
    'searcherfmapping.php',
    'manageunitdetails.php',
    'showaudittrail.php',
    'manageroutinetests.php',
    'searchvendors.php',
    'manageerfmappingdetails.php',
    'searchfiltergroups.php',
    'manageprotocols.php',
    'generateplannedvsactualrtrpt.php',
    'searchrooms.php',
    'updateschedulestatus.php',
    'manageroomdetails.php'
];

function fixPermissions($filename) {
    $filepath = __DIR__ . '/' . $filename;

    if (!file_exists($filepath)) {
        return "File not found: $filename";
    }

    // Fix permissions
    if (!chmod($filepath, 0644)) {
        return "Failed to fix permissions for: $filename";
    }

    return "Permissions fixed for: $filename";
}

function updateFileContent($filename) {
    $filepath = __DIR__ . '/' . $filename;

    if (!file_exists($filepath)) {
        return ['status' => 'error', 'message' => "File not found: $filename"];
    }

    $content = file_get_contents($filepath);
    if ($content === false) {
        return ['status' => 'error', 'message' => "Could not read file: $filename"];
    }

    // Check if already optimized
    if (strpos($content, 'OptimizedSessionValidation') !== false) {
        return ['status' => 'info', 'message' => "Already optimized: $filename"];
    }

    $originalContent = $content;

    // Pattern 1: Standard pattern with auth check and session validation
    $pattern1 = '/\/\/\s*Check for proper authentication[^\n]*\n\s*if\s*\(!isset\(\$_SESSION\[\'logged_in_user\'\]\)[^}]+\}\s*\/\/\s*Validate session timeout[^\n]*\n\s*require_once\([\'"]core\/security\/session_timeout_middleware\.php[\'"]\);\s*validateActiveSession\(\);/s';

    $replacement1 = "// Optimized session validation\nrequire_once('core/security/optimized_session_validation.php');\nOptimizedSessionValidation::validateOnce();";

    // Pattern 2: Simple session timeout pattern
    $pattern2 = '/require_once\([\'"]core\/security\/session_timeout_middleware\.php[\'"]\);\s*validateActiveSession\(\);/';

    $replacement2 = "require_once('core/security/optimized_session_validation.php');\nOptimizedSessionValidation::validateOnce();";

    // Pattern 3: With session validation
    $pattern3 = '/require_once\([\'"]core\/security\/session_timeout_middleware\.php[\'"]\);\s*validateActiveSession\(\);\s*\/\/[^\n]*\n\s*require_once\([\'"]core\/security\/session_validation\.php[\'"]\);\s*validateUserSession\(\);/';

    $replacement3 = "require_once('core/security/optimized_session_validation.php');\nOptimizedSessionValidation::validateOnce();";

    // Try patterns in order of specificity
    $content = preg_replace($pattern1, $replacement1, $content);

    if ($content === $originalContent) {
        $content = preg_replace($pattern3, $replacement3, $content);
    }

    if ($content === $originalContent) {
        $content = preg_replace($pattern2, $replacement2, $content);
    }

    // Check if any changes were made
    if ($content !== $originalContent) {
        // Create backup
        $backupPath = $filepath . '.backup.' . date('Y-m-d-H-i-s');
        if (file_put_contents($backupPath, $originalContent) === false) {
            return ['status' => 'warning', 'message' => "Updated $filename but could not create backup"];
        }

        // Write updated content
        if (file_put_contents($filepath, $content) !== false) {
            return ['status' => 'success', 'message' => "Successfully updated: $filename"];
        } else {
            return ['status' => 'error', 'message' => "Failed to write updated content: $filename"];
        }
    } else {
        return ['status' => 'warning', 'message' => "No pattern matched for: $filename"];
    }
}

// Step 1: Fix permissions for all files
echo $isCLI ? "\nStep 1: Fixing file permissions...\n" : "<h2>Step 1: Fixing File Permissions</h2>\n";

$permissionResults = [];
foreach ($filesToUpdate as $file) {
    $result = fixPermissions($file);
    $permissionResults[] = $result;
    echo $isCLI ? "$result\n" : "<p>$result</p>\n";
}

// Step 2: Update file content
echo $isCLI ? "\nStep 2: Updating file content...\n" : "<h2>Step 2: Updating File Content</h2>\n";

$stats = [
    'success' => 0,
    'warning' => 0,
    'error' => 0,
    'info' => 0
];

$updateResults = [];

foreach ($filesToUpdate as $file) {
    $result = updateFileContent($file);
    $stats[$result['status']]++;
    $updateResults[] = $result;

    $icon = '';
    if (!$isCLI) {
        $icon = $result['status'] === 'success' ? '✅ ' :
                ($result['status'] === 'warning' ? '⚠️ ' :
                ($result['status'] === 'error' ? '❌ ' : 'ℹ️ '));
    }

    $message = $icon . $result['message'];

    if ($isCLI) {
        echo "$message\n";
    } else {
        $class = $result['status'];
        echo "<p class='$class'>$message</p>\n";
    }
}

// Summary
echo $isCLI ? "\n" . str_repeat("=", 50) . "\n" : "<hr>\n";
echo $isCLI ? "SUMMARY\n" : "<h2>Summary</h2>\n";
echo $isCLI ? str_repeat("=", 50) . "\n" : "";

$total = count($filesToUpdate);
foreach ($stats as $status => $count) {
    $percentage = ($count / $total) * 100;
    $statusDisplay = ucfirst($status);

    if ($isCLI) {
        echo sprintf("%-15s: %2d files (%5.1f%%)\n", $statusDisplay, $count, $percentage);
    } else {
        echo "<p><strong>$statusDisplay:</strong> $count files (" . number_format($percentage, 1) . "%)</p>\n";
    }
}

// Next steps
echo $isCLI ? "\nNEXT STEPS:\n" : "<h2>Next Steps</h2>\n";
$nextSteps = [
    "Test the updated files in your development environment",
    "Run test_system_wide_optimization.php to verify all changes",
    "Use performance_benchmark.php to measure improvements",
    "Monitor system performance after deployment",
    "Keep backup files until testing is complete"
];

if ($isCLI) {
    foreach ($nextSteps as $i => $step) {
        echo ($i + 1) . ". $step\n";
    }
} else {
    echo "<ol>\n";
    foreach ($nextSteps as $step) {
        echo "<li>$step</li>\n";
    }
    echo "</ol>\n";
}

// Files that need manual attention
$needsAttention = [];
foreach ($updateResults as $result) {
    if ($result['status'] === 'warning' || $result['status'] === 'error') {
        $needsAttention[] = $result['message'];
    }
}

if (!empty($needsAttention)) {
    echo $isCLI ? "\nFILES NEEDING MANUAL ATTENTION:\n" : "<h2>Files Needing Manual Attention</h2>\n";

    if ($isCLI) {
        foreach ($needsAttention as $issue) {
            echo "- $issue\n";
        }
    } else {
        echo "<ul>\n";
        foreach ($needsAttention as $issue) {
            echo "<li>$issue</li>\n";
        }
        echo "</ul>\n";
    }
}

echo $isCLI ? "\nOptimization completed at " . date('Y-m-d H:i:s') . "\n" : "<p><em>Optimization completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>