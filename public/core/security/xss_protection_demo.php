<?php
/**
 * XSS Protection Demonstration for ProVal HVAC Security
 * 
 * This file demonstrates the comprehensive XSS protection system
 * and shows how to properly use the XSS prevention utilities.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

// Include XSS protection (this auto-initializes the middleware)
require_once '../security/xss_integration_middleware.php';
require_once '../config/config.php';

session_start();

echo "<h1>ProVal HVAC - XSS Protection System Demo</h1>\n";

// Check if XSS protection is active
$isProtected = XSSIntegrationMiddleware::isInitialized() ? 'ACTIVE' : 'INACTIVE';
$statusColor = XSSIntegrationMiddleware::isInitialized() ? 'green' : 'red';

echo "<div style='background-color: #f0f0f0; padding: 10px; border-left: 5px solid {$statusColor}; margin-bottom: 20px;'>\n";
echo "<h2>XSS Protection Status: <span style='color: {$statusColor}'>{$isProtected}</span></h2>\n";
echo "<p>The XSS protection middleware is currently <strong>{$isProtected}</strong> for this request.</p>\n";
echo "</div>\n";

// Get filtering statistics
$stats = XSSIntegrationMiddleware::getFilteringStats();
echo "<h2>Current Request Filtering Statistics</h2>\n";
echo "<ul>\n";
echo "<li><strong>Inputs Processed:</strong> {$stats['inputs_processed']}</li>\n";
echo "<li><strong>Inputs Modified:</strong> {$stats['inputs_modified']}</li>\n";
echo "<li><strong>Sources Processed:</strong></li>\n";
echo "<ul>\n";
foreach ($stats['sources_processed'] as $source => $data) {
    echo "<li>{$source}: {$data['total']} total, {$data['modified']} modified</li>\n";
}
echo "</ul>\n";
echo "</ul>\n";

echo "<h2>XSS Protection Features</h2>\n";
echo "<p>The system provides multiple layers of XSS protection:</p>\n";
echo "<ul>\n";
echo "<li><strong>Automatic Input Filtering:</strong> All \$_GET, \$_POST, and \$_COOKIE data is automatically filtered</li>\n";
echo "<li><strong>Context-Aware Output Encoding:</strong> Different encoding for HTML, attributes, JavaScript, CSS, and URLs</li>\n";
echo "<li><strong>XSS Pattern Detection:</strong> Advanced pattern matching to detect potential XSS attacks</li>\n";
echo "<li><strong>Safe Input Functions:</strong> Type-validated input retrieval with fallback defaults</li>\n";
echo "<li><strong>Security Logging:</strong> All XSS attempts and filtering actions are logged</li>\n";
echo "<li><strong>Content Security Policy:</strong> Browser-level XSS protection through CSP headers</li>\n";
echo "</ul>\n";

echo "<h2>Testing XSS Protection</h2>\n";

// Test data with potential XSS
$testData = [
    'basic_script' => '<script>alert("XSS")</script>',
    'img_onerror' => '<img src="x" onerror="alert(\'XSS\')">',
    'javascript_url' => '<a href="javascript:alert(\'XSS\')">Click me</a>',
    'event_handler' => '<div onclick="alert(\'XSS\')">Click me</div>',
    'iframe_embed' => '<iframe src="javascript:alert(\'XSS\')"></iframe>',
    'css_expression' => '<div style="background:expression(alert(\'XSS\'))">Test</div>',
    'encoded_script' => '&lt;script&gt;alert("XSS")&lt;/script&gt;',
    'svg_script' => '<svg><script>alert("XSS")</script></svg>',
    'form_action' => '<form action="javascript:alert(\'XSS\')"><input type="submit"></form>',
    'meta_refresh' => '<meta http-equiv="refresh" content="0;url=javascript:alert(\'XSS\')">',
    'safe_content' => 'This is <strong>safe</strong> content with <em>emphasis</em>'
];

echo "<h3>XSS Pattern Detection Results</h3>\n";
echo "<table border='1' cellpadding='5' cellspacing='0' style='width:100%;'>\n";
echo "<tr><th>Test Case</th><th>Input</th><th>XSS Detected</th><th>Filtered Output</th></tr>\n";

foreach ($testData as $testName => $input) {
    $isXSS = XSSPrevention::detectXSS($input) ? 'YES' : 'NO';
    $detectionColor = XSSPrevention::detectXSS($input) ? 'red' : 'green';
    $cleanedInput = XSSPrevention::cleanInput($input, false, false);
    
    echo "<tr>\n";
    echo "<td><strong>" . htmlspecialchars($testName) . "</strong></td>\n";
    echo "<td><code>" . htmlspecialchars(substr($input, 0, 50)) . "...</code></td>\n";
    echo "<td style='color: {$detectionColor}'><strong>{$isXSS}</strong></td>\n";
    echo "<td><code>" . htmlspecialchars(substr($cleanedInput, 0, 50)) . "...</code></td>\n";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<h3>Context-Aware Output Encoding Demo</h3>\n";
$sampleData = 'User input: <script>alert("test")</script> & "quotes"';

echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th>Context</th><th>Function</th><th>Encoded Output</th></tr>\n";

$contexts = [
    'html' => 'safe_html()',
    'attr' => 'safe_attr()',
    'js' => 'safe_js()',
    'css' => 'encode($data, "css")',
    'url' => 'safe_url()'
];

foreach ($contexts as $context => $function) {
    $encoded = XSSPrevention::encode($sampleData, $context);
    echo "<tr>\n";
    echo "<td><strong>{$context}</strong></td>\n";
    echo "<td><code>{$function}</code></td>\n";
    echo "<td><code>" . htmlspecialchars($encoded) . "</code></td>\n";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<h3>Safe Input Functions Demo</h3>\n";
echo "<p>The system provides type-safe input functions:</p>\n";
echo "<ul>\n";
echo "<li><code>safe_get('param', 'int', 0)</code> - Get integer from GET parameters</li>\n";
echo "<li><code>safe_post('name', 'string', '')</code> - Get string from POST data</li>\n";
echo "<li><code>safe_input('email', 'POST', 'email')</code> - Get validated email</li>\n";
echo "<li><code>safe_input('file', 'POST', 'filename')</code> - Get safe filename</li>\n";
echo "</ul>\n";

// Demo current request data
if (!empty($_GET) || !empty($_POST)) {
    echo "<h3>Current Request Data (Filtered)</h3>\n";
    
    if (!empty($_GET)) {
        echo "<h4>GET Parameters:</h4>\n";
        echo "<pre>" . htmlspecialchars(print_r($_GET, true)) . "</pre>\n";
    }
    
    if (!empty($_POST)) {
        echo "<h4>POST Parameters:</h4>\n";
        echo "<pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>\n";
    }
}

echo "<h2>Content Security Policy (CSP)</h2>\n";
echo "<p>The following CSP header is automatically applied to all pages:</p>\n";

// Get current CSP policy
$headers = headers_list();
$cspHeader = '';
foreach ($headers as $header) {
    if (stripos($header, 'Content-Security-Policy:') === 0) {
        $cspHeader = $header;
        break;
    }
}

if ($cspHeader) {
    echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    echo htmlspecialchars($cspHeader);
    echo "</pre>\n";
} else {
    echo "<p style='color: orange;'>⚠️ CSP header not found. Make sure security_middleware.php is included.</p>\n";
}

echo "<h2>Implementation Guidelines</h2>\n";
echo "<h3>For Developers:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Always include XSS middleware:</strong><br><code>require_once('../security/xss_integration_middleware.php');</code></li>\n";
echo "<li><strong>Use safe input functions:</strong><br><code>\$name = safe_post('name', 'string', '');</code></li>\n";
echo "<li><strong>Use context-aware output:</strong><br><code>echo safe_html(\$userInput);</code></li>\n";
echo "<li><strong>Validate critical data:</strong><br><code>if (XSSPrevention::detectXSS(\$input)) { /* handle attack */ }</code></li>\n";
echo "<li><strong>Include security middleware:</strong><br><code>require_once('../security/security_middleware.php');</code></li>\n";
echo "</ol>\n";

echo "<h3>Security Headers Applied:</h3>\n";
$securityHeaders = [
    'X-Content-Type-Options: nosniff',
    'X-Frame-Options: DENY', 
    'X-XSS-Protection: 1; mode=block',
    'Content-Security-Policy: [Restrictive policy]',
    'Referrer-Policy: strict-origin-when-cross-origin',
    'Permissions-Policy: geolocation=(), microphone=(), camera=()'
];

echo "<ul>\n";
foreach ($securityHeaders as $header) {
    echo "<li><code>" . htmlspecialchars($header) . "</code></li>\n";
}
echo "</ul>\n";

echo "<h2>Test Form</h2>\n";
echo "<p>Use this form to test XSS protection with your own input:</p>\n";
echo "<form method='POST' action='xss_protection_demo.php'>\n";
echo "<textarea name='test_input' rows='4' cols='60' placeholder='Enter test input here...'>" . safe_html(safe_post('test_input', 'string', '')) . "</textarea><br><br>\n";
echo "<input type='submit' value='Test XSS Protection'>\n";
echo "</form>\n";

if (!empty($_POST['test_input'])) {
    $testInput = safe_post('test_input', 'string', '');
    $rawInput = XSSIntegrationMiddleware::getRawInput('test_input', 'POST');
    
    echo "<h3>Test Results:</h3>\n";
    echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
    echo "<tr><th>Aspect</th><th>Result</th></tr>\n";
    echo "<tr><td><strong>Original Input</strong></td><td><code>" . htmlspecialchars($rawInput) . "</code></td></tr>\n";
    echo "<tr><td><strong>Filtered Input</strong></td><td><code>" . htmlspecialchars($testInput) . "</code></td></tr>\n";
    echo "<tr><td><strong>XSS Detected</strong></td><td>" . (XSSPrevention::detectXSS($rawInput) ? '🚨 YES' : '✅ NO') . "</td></tr>\n";
    echo "<tr><td><strong>Input Modified</strong></td><td>" . (XSSIntegrationMiddleware::wasInputModified('test_input', 'POST') ? '⚠️ YES' : '✅ NO') . "</td></tr>\n";
    echo "<tr><td><strong>Safe HTML Output</strong></td><td>" . safe_html($testInput) . "</td></tr>\n";
    echo "</table>\n";
}

echo "<hr>\n";
echo "<p><em>This demonstration shows the comprehensive XSS protection system in ProVal HVAC. All user input is automatically filtered and logged for security.</em></p>\n";
?>