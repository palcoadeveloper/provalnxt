<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

echo "<h2>Session Debug Information</h2>";
echo "<h3>Current Session Variables:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Required Variables Check:</h3>";
echo "<ul>";
echo "<li>logged_in_user: " . (isset($_SESSION['logged_in_user']) ? $_SESSION['logged_in_user'] : 'NOT SET') . "</li>";
echo "<li>vendor_id: " . (isset($_SESSION['vendor_id']) ? $_SESSION['vendor_id'] : 'NOT SET') . "</li>";
echo "<li>unit_id: " . (isset($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 'NOT SET') . "</li>";
echo "<li>department_id: " . (isset($_SESSION['department_id']) ? $_SESSION['department_id'] : 'NOT SET') . "</li>";
echo "<li>is_dept_head: " . (isset($_SESSION['is_dept_head']) ? $_SESSION['is_dept_head'] : 'NOT SET') . "</li>";
echo "<li>is_qa_head: " . (isset($_SESSION['is_qa_head']) ? $_SESSION['is_qa_head'] : 'NOT SET') . "</li>";
echo "</ul>";

// Test database connection
echo "<h3>Database Connection Test:</h3>";
try {
    include_once("core/config/db.class.php");
    $testQuery = "SELECT 1 as test_value";
    $result = DB::query($testQuery);
    echo "Database connection: <span style='color:green'>OK</span><br>";
    echo "Test query result: " . print_r($result, true);
} catch (Exception $e) {
    echo "Database connection: <span style='color:red'>ERROR</span><br>";
    echo "Error: " . $e->getMessage();
}
?>