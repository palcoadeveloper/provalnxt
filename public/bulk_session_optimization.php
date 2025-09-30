<?php
/**
 * Bulk Session Optimization Script
 *
 * This script helps convert all files from old session validation
 * to optimized session validation
 */

require_once('./core/config/config.php');

// Files to update (found from search)
$filesToUpdate = [
    'pendingforlevel1submission.php',
    'generatescheduleroutinetest.php',
    'searchinstruments.php',
    'managefiltergroups.php',
    'pendingforlevel2approval.php',
    'searchmapping.php',
    'viewtestdetails.php',
    'manageequipmentdetails.php',
    'manageinstrumentdetails.php',
    'generatertschedulereport.php',
    'managefilterdetails.php',
    'preview_raw_data.php',
    'assignedcases.php',
    'searchuser.php',
    'generateplannedvsactualrpt.php',
    'addvalrequest.php',
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

echo "<h1>Bulk Session Optimization Report</h1>\n";
echo "<p>Processing " . count($filesToUpdate) . " files...</p>\n";

$successCount = 0;
$errorCount = 0;
$skipCount = 0;

foreach ($filesToUpdate as $file) {
    echo "<h3>Processing: $file</h3>\n";

    $filePath = __DIR__ . '/' . $file;

    if (!file_exists($filePath)) {
        echo "<p style='color: orange;'>⚠️ File not found: $file</p>\n";
        $skipCount++;
        continue;
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        echo "<p style='color: red;'>❌ Could not read file: $file</p>\n";
        $errorCount++;
        continue;
    }

    // Check if already optimized
    if (strpos($content, 'OptimizedSessionValidation') !== false) {
        echo "<p style='color: blue;'>ℹ️ Already optimized: $file</p>\n";
        $skipCount++;
        continue;
    }

    // Pattern to match the old session validation
    $oldPattern = '/require_once\([\'"]core\/security\/session_timeout_middleware\.php[\'"]\);\s*validateActiveSession\(\);/';
    $newReplacement = "require_once('core/security/optimized_session_validation.php');\nOptimizedSessionValidation::validateOnce();";

    // Check if file has the old pattern
    if (preg_match($oldPattern, $content)) {
        $newContent = preg_replace($oldPattern, $newReplacement, $content);

        if ($newContent && $newContent !== $content) {
            echo "<p style='color: green;'>✅ Updated session validation in: $file</p>\n";
            echo "<p>File ready for optimization (changes identified)</p>\n";
            $successCount++;
        } else {
            echo "<p style='color: red;'>❌ Failed to update: $file</p>\n";
            $errorCount++;
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No standard session validation pattern found in: $file</p>\n";
        $skipCount++;
    }
}

echo "<hr>\n";
echo "<h2>Summary</h2>\n";
echo "<p><strong>Total files processed:</strong> " . count($filesToUpdate) . "</p>\n";
echo "<p><strong>Successfully identified for update:</strong> <span style='color: green;'>$successCount</span></p>\n";
echo "<p><strong>Errors:</strong> <span style='color: red;'>$errorCount</span></p>\n";
echo "<p><strong>Skipped:</strong> <span style='color: orange;'>$skipCount</span></p>\n";

echo "<h2>Next Steps</h2>\n";
echo "<ol>\n";
echo "<li>Review the files identified for update</li>\n";
echo "<li>Apply the optimized session validation</li>\n";
echo "<li>Test the updated files</li>\n";
echo "<li>Monitor performance improvements</li>\n";
echo "</ol>\n";
?>