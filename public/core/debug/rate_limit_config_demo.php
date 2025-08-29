<?php
/**
 * Rate Limiting Configuration Demo
 * 
 * This demonstrates how to view and manage rate limiting configuration
 * using the configurable rate limiting system.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

// Include required files
require_once '../config/config.php';
require_once '../security/rate_limiting_utils.php';

// For demonstration purposes - in production this would require admin authentication
session_start();

echo "<h1>ProVal HVAC - Rate Limiting Configuration</h1>\n";

// Check if rate limiting is enabled
$rateLimitingEnabled = RateLimiter::isRateLimitingEnabled();
$enabledStatus = $rateLimitingEnabled ? 'ENABLED' : 'DISABLED';
$statusColor = $rateLimitingEnabled ? 'green' : 'red';

echo "<div style='background-color: #f0f0f0; padding: 10px; border-left: 5px solid {$statusColor}; margin-bottom: 20px;'>\n";
echo "<h2>Global Status: <span style='color: {$statusColor}'>{$enabledStatus}</span></h2>\n";
echo "<p>Rate limiting is currently <strong>{$enabledStatus}</strong> for this system.</p>\n";
echo "</div>\n";

echo "<h2>Current Configuration</h2>\n";
echo "<p>Rate limiting rules can be configured in three ways:</p>\n";
echo "<ul>\n";
echo "<li><strong>Global Enable/Disable</strong>: RATE_LIMITING_ENABLED constant or database override</li>\n";
echo "<li><strong>config.php</strong>: Default values using constants (RATE_LIMIT_*)</li>\n";
echo "<li><strong>Database</strong>: Runtime overrides stored in security_config table</li>\n";
echo "</ul>\n";

// Get current configuration
$config = RateLimiter::getCurrentConfiguration();

echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th rowspan='2'>Action</th><th rowspan='2'>Description</th><th colspan='3'>Per-IP Limits</th><th colspan='3'>System-Wide Limits</th><th rowspan='2'>Source</th></tr>\n";
echo "<tr><th>Max</th><th>Window</th><th>Lockout</th><th>Max</th><th>Window</th><th>Lockout</th></tr>\n";

foreach ($config as $action => $settings) {
    $rules = $settings['current_rules'];
    $source = $settings['source'];
    $description = $settings['description'];
    
    echo "<tr>\n";
    echo "<td><code>{$action}</code></td>\n";
    echo "<td>{$description}</td>\n";
    
    // Per-IP limits
    if (isset($rules['per_ip'])) {
        echo "<td>{$rules['per_ip']['max']}</td>\n";
        echo "<td>" . formatDuration($rules['per_ip']['window']) . "</td>\n";
        echo "<td>" . formatDuration($rules['per_ip']['lockout']) . "</td>\n";
    } else {
        // Fallback for old format
        echo "<td>{$rules['max']}</td>\n";
        echo "<td>" . formatDuration($rules['window']) . "</td>\n";
        echo "<td>" . formatDuration($rules['lockout']) . "</td>\n";
    }
    
    // System-wide limits
    if (isset($rules['system_wide'])) {
        echo "<td>{$rules['system_wide']['max']}</td>\n";
        echo "<td>" . formatDuration($rules['system_wide']['window']) . "</td>\n";
        echo "<td>" . formatDuration($rules['system_wide']['lockout']) . "</td>\n";
    } else {
        echo "<td>N/A</td>\n";
        echo "<td>N/A</td>\n";
        echo "<td>N/A</td>\n";
    }
    
    echo "<td><span style='color: " . ($source === 'database' ? 'red' : 'green') . "'>{$source}</span></td>\n";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<h2>Configuration Examples</h2>\n";

echo "<h3>View Configuration in PHP:</h3>\n";
echo "<pre><code>\n";
echo "// Get current configuration\n";
echo "\$config = RateLimiter::getCurrentConfiguration();\n";
echo "print_r(\$config);\n";
echo "</code></pre>\n";

echo "<h3>Update Configuration via Database:</h3>\n";
echo "<pre><code>\n";
echo "// Update login attempts rate limiting (dual-layer)\n";
echo "\$success = RateLimiter::updateActionRules('login_attempts', [\n";
echo "    'per_ip' => [\n";
echo "        'max' => 3,      // 3 attempts per IP\n";
echo "        'window' => 600,   // in 10 minutes\n";
echo "        'lockout' => 3600  // lock IP for 1 hour\n";
echo "    ],\n";
echo "    'system_wide' => [\n";
echo "        'max' => 500,     // 500 attempts system-wide\n";
echo "        'window' => 600,    // in 10 minutes\n";
echo "        'lockout' => 1800   // lock system for 30 minutes\n";
echo "    ]\n";
echo "]);\n";
echo "</code></pre>\n";

echo "<h3>Reset to Configuration Defaults:</h3>\n";
echo "<pre><code>\n";
echo "// Reset to config.php defaults\n";
echo "\$success = RateLimiter::resetToDefaults('login_attempts');\n";
echo "</code></pre>\n";

echo "<h3>Enable/Disable Rate Limiting Globally:</h3>\n";
echo "<pre><code>\n";
echo "// Check if rate limiting is enabled\n";
echo "\$isEnabled = RateLimiter::isRateLimitingEnabled();\n";
echo "\n";
echo "// Enable rate limiting globally\n";
echo "\$success = RateLimiter::enableRateLimiting();\n";
echo "\n";
echo "// Disable rate limiting globally\n";
echo "\$success = RateLimiter::disableRateLimiting();\n";
echo "</code></pre>\n";

echo "<h2>Configuration Constants in config.php</h2>\n";
echo "<p>The following constants control the default rate limiting behavior:</p>\n";

echo "<h4>Global Control</h4>\n";
echo "<ul>\n";
$enabledValue = defined('RATE_LIMITING_ENABLED') ? (RATE_LIMITING_ENABLED ? 'true' : 'false') : 'Not defined';
echo "<li><code>RATE_LIMITING_ENABLED</code>: {$enabledValue}</li>\n";
echo "</ul>\n";

$constants = [
    'Login Attempts' => ['RATE_LIMIT_LOGIN_MAX', 'RATE_LIMIT_LOGIN_WINDOW', 'RATE_LIMIT_LOGIN_LOCKOUT'],
    'Password Reset' => ['RATE_LIMIT_PASSWORD_RESET_MAX', 'RATE_LIMIT_PASSWORD_RESET_WINDOW', 'RATE_LIMIT_PASSWORD_RESET_LOCKOUT'],
    'API Requests' => ['RATE_LIMIT_API_MAX', 'RATE_LIMIT_API_WINDOW', 'RATE_LIMIT_API_LOCKOUT'],
    'File Uploads' => ['RATE_LIMIT_FILE_UPLOAD_MAX', 'RATE_LIMIT_FILE_UPLOAD_WINDOW', 'RATE_LIMIT_FILE_UPLOAD_LOCKOUT'],
    'Form Submissions' => ['RATE_LIMIT_FORM_SUBMISSION_MAX', 'RATE_LIMIT_FORM_SUBMISSION_WINDOW', 'RATE_LIMIT_FORM_SUBMISSION_LOCKOUT']
];

foreach ($constants as $category => $constantList) {
    echo "<h4>{$category}</h4>\n";
    echo "<ul>\n";
    foreach ($constantList as $constant) {
        $value = defined($constant) ? constant($constant) : 'Not defined';
        if (is_numeric($value) && $constant !== 'RATE_LIMIT_LOGIN_MAX' && 
            $constant !== 'RATE_LIMIT_PASSWORD_RESET_MAX' && 
            $constant !== 'RATE_LIMIT_API_MAX' && 
            $constant !== 'RATE_LIMIT_FILE_UPLOAD_MAX' && 
            $constant !== 'RATE_LIMIT_FORM_SUBMISSION_MAX') {
            $value = formatDuration($value);
        }
        echo "<li><code>{$constant}</code>: {$value}</li>\n";
    }
    echo "</ul>\n";
}

echo "<h2>Rate Limiting Statistics</h2>\n";
$stats = RateLimiter::getStatistics();
echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th>Metric</th><th>Per-IP</th><th>System-Wide</th><th>Total</th></tr>\n";
echo "<tr><td>Active Lockouts</td><td>{$stats['active_per_ip_lockouts']}</td><td>{$stats['active_system_wide_lockouts']}</td><td>" . ($stats['active_per_ip_lockouts'] + $stats['active_system_wide_lockouts']) . "</td></tr>\n";
echo "<tr><td>Active Rate Limits</td><td>{$stats['active_per_ip_rate_limits']}</td><td>{$stats['active_system_wide_rate_limits']}</td><td>" . ($stats['active_per_ip_rate_limits'] + $stats['active_system_wide_rate_limits']) . "</td></tr>\n";
echo "<tr><td colspan='3'>Total Stored Data</td><td>{$stats['total_stored_data']}</td></tr>\n";
echo "</table>\n";

/**
 * Format duration in seconds to human readable format
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        return ($seconds / 60) . ' minutes';
    } else {
        return ($seconds / 3600) . ' hours';
    }
}

echo "<hr>\n";
echo "<p><em>This is a demonstration script. In production, access to rate limiting configuration should be restricted to administrators only.</em></p>\n";
?>