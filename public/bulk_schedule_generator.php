<?php
/**
 * Bulk Validation Schedule Generator
 *
 * Automatically generates validation schedules and PDFs for all active units
 * with validation_scheduling_logic = 'fixed' for the next calendar year.
 *
 * Usage:
 *   php bulk_schedule_generator.php [--year=2025] [--test] [--unit-id=123]
 *
 * Parameters:
 *   --year     : Target year (default: next year)
 *   --test     : Dry run mode (validation only, no generation)
 *   --unit-id  : Process single unit (for testing)
 *
 * Exit Codes:
 *   0 = Complete success
 *   1 = Partial failure (some units failed)
 *   2 = Total failure (setup/critical errors)
 */

// Support both CLI and web execution
if (php_sapi_name() !== 'cli') {
    // Web access configuration
    require_once('./core/config/config.php');
    require_once('./core/config/db.class.php');

    // Set appropriate limits for web execution
    set_time_limit(1800); // 30 minutes
    ini_set('memory_limit', '512M');

    // Suppress PHP warnings for cleaner web output
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', 0);

    // Output headers for web display
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Bulk Schedule Generator</title></head><body>";
    echo "<h2>ProVal Bulk Schedule Generator - Web Interface</h2>";
    echo "<hr><pre>";
} else {
    // Set error reporting for CLI
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Load configuration
require_once(__DIR__ . '/core/config/config.php');
require_once(__DIR__ . '/core/config/db.class.php');

// Set timezone
date_default_timezone_set("Asia/Kolkata");

/**
 * Bulk Schedule Generator Class
 */
class BulkScheduleGenerator
{
    private $logFile;
    private $targetYear;
    private $testMode;
    private $singleUnitId;
    private $operationId;
    private $stats;

    public function __construct($targetYear = null, $testMode = false, $singleUnitId = null)
    {
        $this->targetYear = $targetYear ?: (date('Y') + 1);
        $this->testMode = $testMode;
        $this->singleUnitId = $singleUnitId;
        $this->operationId = uniqid('bulk_gen_');
        $this->stats = [
            'total_units' => 0,
            'successful' => 0,
            'warnings' => 0,
            'errors' => 0,
            'skipped' => 0
        ];

        $this->initializeLogging();
    }

    /**
     * Initialize logging system
     */
    private function initializeLogging()
    {
        $logsDir = __DIR__ . '/logs';

        // Try to create logs directory if it doesn't exist
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }

        // Try to set up log file, but continue even if it fails
        if (is_dir($logsDir) && is_writable($logsDir)) {
            $timestamp = date('Y-m-d_H-i-s');
            $this->logFile = $logsDir . "/bulk_schedule_generation_{$timestamp}.log";
        } else {
            $this->logFile = null;
            if (php_sapi_name() !== 'cli') {
                echo "<br><em>Warning: Cannot write to logs directory. Output will be shown on screen only.</em><br><br>";
            }
        }

        $this->log('INFO', "Bulk Schedule Generator Started - Operation ID: {$this->operationId}");
        $this->log('INFO', "Target Year: {$this->targetYear}");
        $this->log('INFO', "Test Mode: " . ($this->testMode ? 'YES' : 'NO'));
        if ($this->singleUnitId) {
            $this->log('INFO', "Single Unit Mode: {$this->singleUnitId}");
        }
    }

    /**
     * Log message with timestamp and level
     */
    private function log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' | ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        // Try to write to log file, but don't fail if we can't
        if ($this->logFile) {
            $result = @file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);

            // If file write fails, add a note to the console output
            if ($result === false && php_sapi_name() !== 'cli') {
                echo "<br><em>Note: Log file write failed - check permissions on logs directory</em><br>";
            }
        }

        // Always output to console/browser
        if (php_sapi_name() !== 'cli') {
            // For web output, convert newlines to HTML breaks for better readability
            echo nl2br(htmlspecialchars($logEntry));
        } else {
            echo $logEntry;
        }
    }

    /**
     * Main execution method
     */
    public function run()
    {
        try {
            // Discover units to process
            $units = $this->discoverUnits();

            if (empty($units)) {
                $this->log('WARNING', 'No units found for processing');
                return 0;
            }

            $this->stats['total_units'] = count($units);
            $this->log('INFO', "Found {$this->stats['total_units']} active units with fixed scheduling logic");

            // Process each unit
            foreach ($units as $unit) {
                $this->processUnit($unit);
            }

            // Generate final summary
            $this->generateSummary();

            // Return appropriate exit code
            if ($this->stats['errors'] > 0 && $this->stats['successful'] === 0) {
                return 2; // Total failure
            } elseif ($this->stats['errors'] > 0 || $this->stats['warnings'] > 0) {
                return 1; // Partial failure
            } else {
                return 0; // Complete success
            }

        } catch (Exception $e) {
            $this->log('ERROR', "Critical error: " . $e->getMessage());
            $this->log('ERROR', "Stack trace: " . $e->getTraceAsString());
            return 2;
        }
    }

    /**
     * Discover units to process
     */
    private function discoverUnits()
    {
        try {
            $whereClause = "WHERE unit_status = 'Active' AND validation_scheduling_logic = 'fixed'";
            $params = [];

            if ($this->singleUnitId) {
                $whereClause .= " AND unit_id = %i";
                $params[] = $this->singleUnitId;
            }

            $query = "SELECT unit_id, unit_name, validation_scheduling_logic FROM units {$whereClause} ORDER BY unit_name";

            if ($params) {
                return DB::query($query, ...$params);
            } else {
                return DB::query($query);
            }

        } catch (Exception $e) {
            $this->log('ERROR', "Failed to discover units: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a single unit
     */
    private function processUnit($unit)
    {
        $unitId = $unit['unit_id'];
        $unitName = $unit['unit_name'];
        $unitContext = ['unit_id' => $unitId, 'unit_name' => $unitName];

        $this->log('INFO', "Processing Unit: {$unitName} (ID: {$unitId})", $unitContext);

        try {
            // Step 1: Validate prerequisites
            if (!$this->validatePrerequisites($unitId, $unitContext)) {
                $this->stats['skipped']++;
                return;
            }

            // Step 2: Validate equipment data
            if (!$this->validateEquipmentData($unitId, $unitContext)) {
                $this->stats['warnings']++;
                return;
            }

            // Step 3: Generate schedule (if not test mode)
            $scheduleId = null;
            if (!$this->testMode) {
                $scheduleId = $this->generateSchedule($unitId, $unitContext);
                if (!$scheduleId) {
                    $this->stats['errors']++;
                    return;
                }
            } else {
                $this->log('INFO', "Test mode: Schedule generation skipped for Unit {$unitId}", $unitContext);
            }

            // Step 4: Generate PDF (if not test mode and schedule was created)
            if (!$this->testMode && $scheduleId) {
                if (!$this->generatePDF($unitId, $scheduleId, $unitContext)) {
                    // PDF generation failed, but schedule was created successfully
                    $this->log('WARNING', "Schedule created but PDF generation failed for Unit {$unitId}", $unitContext);
                    $this->stats['warnings']++;
                    return;
                }
            }

            // Step 5: Log success
            $this->logDatabaseActivity($unitId, $scheduleId, 'success', $unitContext);
            $this->stats['successful']++;
            $this->log('SUCCESS', "Unit {$unitId} processed successfully" . ($scheduleId ? " (Schedule ID: {$scheduleId})" : ''), $unitContext);

        } catch (Exception $e) {
            $this->stats['errors']++;
            $this->log('ERROR', "Failed to process Unit {$unitId}: " . $e->getMessage(), $unitContext);
            $this->logDatabaseActivity($unitId, null, 'error', $unitContext, $e->getMessage());
        }
    }

    /**
     * Validate prerequisites for schedule generation
     */
    private function validatePrerequisites($unitId, $context)
    {
        try {
            // Check if schedule already exists for the target year
            $existingSchedule = DB::queryFirstField(
                "SELECT schedule_id FROM tbl_val_wf_schedule_requests WHERE unit_id = %i AND schedule_year = %i",
                $unitId, $this->targetYear
            );

            if ($existingSchedule) {
                $this->log('WARNING', "Schedule already exists for Unit {$unitId}, Year {$this->targetYear} (Schedule ID: {$existingSchedule})", $context);
                return false;
            }

            return true;

        } catch (Exception $e) {
            $this->log('ERROR', "Prerequisite validation failed for Unit {$unitId}: " . $e->getMessage(), $context);
            return false;
        }
    }

    /**
     * Validate equipment data using existing logic
     */
    private function validateEquipmentData($unitId, $context)
    {
        try {
            // Use the same validation logic as the manual process
            $validation_logic = DB::queryFirstField(
                "SELECT validation_scheduling_logic FROM units WHERE unit_id = %d and unit_status='Active'",
                $unitId
            );

            // Get all active equipment data for validation
            $equipment_list = DB::query(
                "SELECT equipment_id, equipment_code, first_validation_date,
                        validation_frequencies, starting_frequency,
                        equipment_addition_date, validation_frequency
                 FROM equipments
                 WHERE unit_id = %d AND equipment_status = 'Active'",
                $unitId
            );

            if (empty($equipment_list)) {
                $this->log('WARNING', "No active equipment found for Unit {$unitId}", $context);
                return false;
            }

            $missing_data = [];

            // Validate each equipment based on scheduling logic
            foreach ($equipment_list as $equipment) {
                $missing_fields = [];

                if ($validation_logic === 'fixed') {
                    // Fixed Date Logic: Require ALL three fields
                    if (empty($equipment['first_validation_date'])) {
                        $missing_fields[] = 'First Validation Date';
                    }
                    if (empty($equipment['validation_frequencies'])) {
                        $missing_fields[] = 'Validation Frequencies';
                    }
                    if (empty($equipment['starting_frequency'])) {
                        $missing_fields[] = 'Starting Frequency';
                    }
                }

                if (!empty($missing_fields)) {
                    $missing_data[] = [
                        'equipment_code' => $equipment['equipment_code'],
                        'missing_fields' => $missing_fields
                    ];
                }
            }

            if (!empty($missing_data)) {
                $missingInfo = array_map(function($item) {
                    return $item['equipment_code'] . ': ' . implode(', ', $item['missing_fields']);
                }, $missing_data);

                $this->log('WARNING', "Equipment data validation failed for Unit {$unitId}. Missing data: " . implode('; ', $missingInfo), $context);
                return false;
            }

            $this->log('INFO', "Equipment data validation passed for Unit {$unitId} ({$validation_logic} scheduling)", $context);
            return true;

        } catch (Exception $e) {
            $this->log('ERROR', "Equipment validation failed for Unit {$unitId}: " . $e->getMessage(), $context);
            return false;
        }
    }

    /**
     * Generate schedule using stored procedure
     */
    private function generateSchedule($unitId, $context)
    {
        try {
            $this->log('INFO', "Calling USP_FIXED_CREATESCHEDULES({$unitId}, {$this->targetYear})", $context);

            $result = DB::queryFirstField("call USP_FIXED_CREATESCHEDULES (%d,%d)", $unitId, $this->targetYear);

            $this->log('INFO', "Stored procedure result: {$result}", $context);

            // Handle different return values
            if ($result == "current_year_sch_pending") {
                $this->log('WARNING', "Current year validation protocols not complete for Unit {$unitId}", $context);
                return false;
            } elseif ($result == "invalid_year") {
                $this->log('ERROR', "Invalid year specified for Unit {$unitId}", $context);
                return false;
            } elseif ($result == "already_exists") {
                $this->log('WARNING', "Schedule generation already in process for Unit {$unitId}", $context);
                return false;
            } elseif ($result == "current_year_sch_test_pending") {
                $this->log('WARNING', "Current year validation tests not complete for Unit {$unitId}", $context);
                return false;
            } else {
                // Success case - get the schedule ID
                $scheduleId = DB::queryFirstField(
                    "SELECT schedule_id FROM tbl_val_wf_schedule_requests WHERE unit_id=%d AND schedule_year=%d",
                    $unitId, $this->targetYear
                );

                if ($scheduleId) {
                    $this->log('SUCCESS', "Schedule generated successfully for Unit {$unitId}, Schedule ID: {$scheduleId}", $context);
                    return $scheduleId;
                } else {
                    $this->log('ERROR', "Schedule generation reported success but no schedule ID found for Unit {$unitId}", $context);
                    return false;
                }
            }

        } catch (Exception $e) {
            $this->log('ERROR', "Schedule generation failed for Unit {$unitId}: " . $e->getMessage(), $context);
            return false;
        }
    }

    /**
     * Generate PDF using internal cURL call
     */
    private function generatePDF($unitId, $scheduleId, $context)
    {
        try {
            $this->log('INFO', "Generating PDF for Unit {$unitId}, Schedule ID: {$scheduleId}", $context);

            // Prepare cURL request to PDF generation endpoint
            $pdf_url = BASE_URL . "generateschedulereport_minimal.php?unit_id={$unitId}&sch_id={$scheduleId}&sch_year={$this->targetYear}&user_name=" . urlencode('Bulk Generator');

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $pdf_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60, // Increased timeout for bulk operation
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'ProVal-BulkGenerator/1.0'
            ]);

            $output = curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Check for cURL errors
            if ($curl_error) {
                $this->log('ERROR', "PDF Generation cURL Error for Unit {$unitId}: {$curl_error}", $context);
                return false;
            }

            if ($http_code !== 200) {
                $this->log('ERROR', "PDF Generation HTTP Error for Unit {$unitId}: HTTP {$http_code}", $context);
                return false;
            }

            if (empty($output)) {
                $this->log('ERROR', "PDF Generation returned empty response for Unit {$unitId}", $context);
                return false;
            }

            // Success detection logic
            if (strpos($output, 'PDF generated successfully') !== false ||
                strpos($output, '%PDF') !== false ||
                (strpos($output, 'Fatal error') === false &&
                 strpos($output, 'Warning') === false &&
                 strpos($output, 'Error') === false &&
                 !empty(trim($output)))) {

                $this->log('SUCCESS', "PDF generated successfully for Unit {$unitId}", $context);
                return true;
            } else {
                $this->log('ERROR', "PDF Generation failed for Unit {$unitId}. Output: " . substr($output, 0, 500), $context);
                return false;
            }

        } catch (Exception $e) {
            $this->log('ERROR', "PDF generation error for Unit {$unitId}: " . $e->getMessage(), $context);
            return false;
        }
    }

    /**
     * Log database activity for audit trail
     */
    private function logDatabaseActivity($unitId, $scheduleId, $status, $context, $errorMessage = null)
    {
        try {
            $description = "Bulk validation schedule generation - Unit ID: {$unitId}, Target Year: {$this->targetYear}, Status: {$status}";
            if ($scheduleId) {
                $description .= ", Schedule ID: {$scheduleId}";
            }
            if ($errorMessage) {
                $description .= ", Error: " . substr($errorMessage, 0, 200);
            }

            DB::insert('log', [
                'change_type' => 'bulk_validation_schedule_generation',
                'table_name' => 'tbl_val_wf_schedule_requests',
                'change_description' => $description,
                'change_by' => 0, // System user
                'unit_id' => $unitId
            ]);

        } catch (Exception $e) {
            $this->log('ERROR', "Failed to log database activity for Unit {$unitId}: " . $e->getMessage(), $context);
        }
    }

    /**
     * Generate final summary
     */
    private function generateSummary()
    {
        $this->log('INFO', '=== BULK GENERATION SUMMARY ===');
        $this->log('INFO', "Operation ID: {$this->operationId}");
        $this->log('INFO', "Target Year: {$this->targetYear}");
        $this->log('INFO', "Total Units Processed: {$this->stats['total_units']}");
        $this->log('INFO', "Successful: {$this->stats['successful']}");
        $this->log('INFO', "Warnings: {$this->stats['warnings']}");
        $this->log('INFO', "Errors: {$this->stats['errors']}");
        $this->log('INFO', "Skipped: {$this->stats['skipped']}");

        $successRate = $this->stats['total_units'] > 0
            ? round(($this->stats['successful'] / $this->stats['total_units']) * 100, 1)
            : 0;
        $this->log('INFO', "Success Rate: {$successRate}%");
        $this->log('INFO', 'Processing completed at: ' . date('Y-m-d H:i:s'));
    }
}

/**
 * Parse command line arguments or web parameters
 */
function parseArguments($argv)
{
    $options = [
        'year' => null,
        'test' => false,
        'unit-id' => null
    ];

    // Check if running from web (GET parameters)
    if (php_sapi_name() !== 'cli') {
        // Parse web GET parameters
        if (isset($_GET['year'])) {
            $year = (int)$_GET['year'];
            if ($year >= 2020 && $year <= 2050) {
                $options['year'] = $year;
            } else {
                echo "Error: Invalid year specified. Must be between 2020 and 2050.\n";
                exit(2);
            }
        }

        if (isset($_GET['test']) || isset($_GET['dry-run'])) {
            $options['test'] = true;
        }

        if (isset($_GET['unit-id'])) {
            $unitId = (int)$_GET['unit-id'];
            if ($unitId > 0) {
                $options['unit-id'] = $unitId;
            } else {
                echo "Error: Invalid unit ID specified.\n";
                exit(2);
            }
        }

        if (isset($_GET['help'])) {
            echo "Bulk Validation Schedule Generator - Web Interface\n\n";
            echo "Usage: bulk_schedule_generator.php?parameter=value\n\n";
            echo "Parameters:\n";
            echo "  year=YYYY        Target year (default: next year)\n";
            echo "  test=1           Dry run mode (validation only, no generation)\n";
            echo "  unit-id=ID       Process single unit (for testing)\n";
            echo "  help=1           Show this help message\n\n";
            echo "Examples:\n";
            echo "  bulk_schedule_generator.php?year=2026\n";
            echo "  bulk_schedule_generator.php?test=1&year=2026\n";
            echo "  bulk_schedule_generator.php?unit-id=7&year=2026\n\n";
            exit(0);
        }

        return $options;
    }

    // Parse CLI arguments
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if (strpos($arg, '--year=') === 0) {
            $year = (int)substr($arg, 7);
            if ($year >= 2020 && $year <= 2050) {
                $options['year'] = $year;
            } else {
                echo "Error: Invalid year specified. Must be between 2020 and 2050.\n";
                exit(2);
            }
        } elseif ($arg === '--test') {
            $options['test'] = true;
        } elseif (strpos($arg, '--unit-id=') === 0) {
            $unitId = (int)substr($arg, 10);
            if ($unitId > 0) {
                $options['unit-id'] = $unitId;
            } else {
                echo "Error: Invalid unit ID specified.\n";
                exit(2);
            }
        } elseif ($arg === '--help' || $arg === '-h') {
            echo "Bulk Validation Schedule Generator\n\n";
            echo "Usage: php bulk_schedule_generator.php [options]\n\n";
            echo "Options:\n";
            echo "  --year=YYYY      Target year (default: next year)\n";
            echo "  --test           Dry run mode (validation only, no generation)\n";
            echo "  --unit-id=ID     Process single unit (for testing)\n";
            echo "  --help, -h       Show this help message\n\n";
            echo "Exit Codes:\n";
            echo "  0 = Complete success\n";
            echo "  1 = Partial failure (some units failed)\n";
            echo "  2 = Total failure (setup/critical errors)\n\n";
            exit(0);
        } else {
            echo "Error: Unknown argument: $arg\n";
            echo "Use --help for usage information.\n";
            exit(2);
        }
    }

    return $options;
}

// Main execution
try {
    // Ensure $argv is defined for web requests
    if (!isset($argv)) {
        $argv = [];
    }

    $options = parseArguments($argv);

    $generator = new BulkScheduleGenerator(
        $options['year'],
        $options['test'],
        $options['unit-id']
    );

    $exitCode = $generator->run();

    // Close HTML for web output
    if (php_sapi_name() !== 'cli') {
        echo "</pre></body></html>";
    }

    exit($exitCode);

} catch (Exception $e) {
    echo "Critical error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";

    // Close HTML for web output
    if (php_sapi_name() !== 'cli') {
        echo "</pre></body></html>";
    }

    exit(2);
}