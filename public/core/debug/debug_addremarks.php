<?php
// Diagnostic script for addremarks.php issues
session_start();

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
include '../config/config.php';

// Set error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check session status
echo "<h2>Session Diagnostic</h2>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Session Status:</strong> " . session_status() . "<br>";
echo "<strong>User Logged In:</strong> " . (isset($_SESSION['logged_in_user']) ? $_SESSION['logged_in_user'] : 'No') . "<br>";
echo "<strong>User Name:</strong> " . (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Not set') . "<br>";
echo "<strong>User Domain ID:</strong> " . (isset($_SESSION['user_domain_id']) ? $_SESSION['user_domain_id'] : 'Not set') . "<br>";
echo "<strong>CSRF Token:</strong> " . (isset($_SESSION['csrf_token']) ? 'Present' : 'Missing') . "<br>";

// Check database connection
echo "<h2>Database Diagnostic</h2>";
try {
    require_once '../config/db.class.php';
    $testQuery = DB::queryFirstField("SELECT 1 as test");
    echo "<strong>Database Connection:</strong> OK<br>";
    echo "<strong>Test Query Result:</strong> " . $testQuery . "<br>";
} catch (Exception $e) {
    echo "<strong>Database Connection:</strong> FAILED<br>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
}

// Check request details if this is a POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Request Diagnostic</h2>";
    echo "<strong>Request Method:</strong> " . $_SERVER['REQUEST_METHOD'] . "<br>";
    echo "<strong>Content Type:</strong> " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set') . "<br>";
    echo "<strong>POST Fields:</strong><br>";
    foreach ($_POST as $key => $value) {
        if ($key === 'user_password') {
            echo "&nbsp;&nbsp;$key: [HIDDEN]<br>";
        } else {
            echo "&nbsp;&nbsp;$key: " . htmlspecialchars($value) . "<br>";
        }
    }
} else {
    echo "<h2>Test Form</h2>";
    echo '<form method="POST">';
    echo '<input type="hidden" name="csrf_token" value="test_token"><br>';
    echo '<textarea name="user_remark" placeholder="Test remark">Test remark</textarea><br>';
    echo '<input type="password" name="user_password" placeholder="Password"><br>';
    echo '<input type="submit" value="Test Submit"><br>';
    echo '</form>';
}

// Check server environment
echo "<h2>Server Environment</h2>";
echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
echo "<strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "<strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "<strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "<strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "<br>";

echo "<h2>Configuration</h2>";
echo "<strong>Environment:</strong> " . ENVIRONMENT . "<br>";
echo "<strong>Max Login Attempts:</strong> " . MAX_LOGIN_ATTEMPTS . "<br>";
echo "<strong>Security Headers:</strong> " . (ENABLE_SECURITY_HEADERS ? 'Enabled' : 'Disabled') . "<br>";
?> 