<?php
/**
 * Apply Session Optimization Script
 *
 * This script automatically applies optimized session validation
 * to all identified PHP files in the system.
 */

// List of files to update with their specific patterns
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
    'managetestdetails.php',
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
    'manageuserdetails.php',
    'updatetaskdetails.php',
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

function updateFile($filename) {
    $filepath = __DIR__ . '/' . $filename;

    if (!file_exists($filepath)) {
        return "File not found: $filename";
    }

    $content = file_get_contents($filepath);
    if ($content === false) {
        return "Could not read file: $filename";
    }

    // Check if already optimized
    if (strpos($content, 'OptimizedSessionValidation') !== false) {
        return "Already optimized: $filename";
    }

    $originalContent = $content;

    // Pattern 1: Standard pattern with auth check
    $pattern1 = '/\/\/ Check for proper authentication\s*\n\s*if \(!isset\(\$_SESSION\[\'logged_in_user\'\]\) \|\| !isset\(\$_SESSION\[\'user_name\'\]\)\) \{\s*\n\s*session_destroy\(\);\s*\n\s*header\(\'Location: \' \. BASE_URL \. \'login\.php\?msg=session_required\'\);\s*\n\s*exit\(\);\s*\n\s*\}\s*\n\s*\/\/ Validate session timeout\s*\n\s*require_once\(\'core\/security\/session_timeout_middleware\.php\'\);\s*\n\s*validateActiveSession\(\);/';

    $replacement1 = "// Optimized session validation\nrequire_once('core/security/optimized_session_validation.php');\nOptimizedSessionValidation::validateOnce();";

    // Pattern 2: Simple pattern
    $pattern2 = '/require_once\(\'core\/security\/session_timeout_middleware\.php\'\);\s*\n\s*validateActiveSession\(\);/';

    $replacement2 = "require_once('core/security/optimized_session_validation.php');\nOptimizedSessionValidation::validateOnce();";

    // Try pattern 1 first (more specific)
    $content = preg_replace($pattern1, $replacement1, $content);

    // If no change, try pattern 2
    if ($content === $originalContent) {
        $content = preg_replace($pattern2, $replacement2, $content);
    }

    // Check if any changes were made
    if ($content !== $originalContent) {
        // Create backup
        $backupPath = $filepath . '.backup.' . date('Y-m-d-H-i-s');
        file_put_contents($backupPath, $originalContent);

        // Write updated content
        if (file_put_contents($filepath, $content) !== false) {
            return "Successfully updated: $filename (backup created)";
        } else {
            return "Failed to write updated content: $filename";
        }
    } else {
        return "No changes needed: $filename";
    }
}

echo "<h1>Applying Session Optimization to All Files</h1>\n";
echo "<style>
.success { color: green; }
.warning { color: orange; }
.error { color: red; }
.info { color: blue; }
</style>\n";

$stats = [
    'updated' => 0,
    'skipped' => 0,
    'errors' => 0,
    'already_optimized' => 0
];

echo "<h2>Processing " . count($filesToUpdate) . " files...</h2>\n";

foreach ($filesToUpdate as $file) {
    $result = updateFile($file);

    if (strpos($result, 'Successfully updated') === 0) {
        echo "<p class='success'>✅ $result</p>\n";
        $stats['updated']++;
    } elseif (strpos($result, 'Already optimized') === 0) {
        echo "<p class='info'>ℹ️ $result</p>\n";
        $stats['already_optimized']++;
    } elseif (strpos($result, 'No changes needed') === 0) {
        echo "<p class='warning'>⚠️ $result</p>\n";
        $stats['skipped']++;
    } else {
        echo "<p class='error'>❌ $result</p>\n";
        $stats['errors']++;
    }
}

echo "<hr>\n";
echo "<h2>Summary</h2>\n";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Status</th><th>Count</th><th>Percentage</th></tr>\n";

$total = count($filesToUpdate);
foreach ($stats as $status => $count) {
    $percentage = ($count / $total) * 100;
    $statusDisplay = ucfirst(str_replace('_', ' ', $status));
    echo "<tr><td>$statusDisplay</td><td>$count</td><td>" . number_format($percentage, 1) . "%</td></tr>\n";
}

echo "</table>\n";

echo "<h2>Recommendations</h2>\n";
echo "<ul>\n";
echo "<li><strong>Test updated files:</strong> Verify functionality after optimization</li>\n";
echo "<li><strong>Performance monitoring:</strong> Use performance_benchmark.php to measure improvements</li>\n";
echo "<li><strong>Backup management:</strong> Keep backup files until testing is complete</li>\n";
echo "<li><strong>Rollback plan:</strong> Use backups if any issues are discovered</li>\n";
echo "</ul>\n";

echo "<p><em>Optimization completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>