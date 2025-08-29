<?php
// Debug script for units dropdown issue
require_once('./core/config/config.php');
require_once('core/config/db.class.php');

echo "<h2>Units Dropdown Debug</h2>";

// Check if sessions are set
echo "<h3>Session Variables:</h3>";
echo "logged_in_user: " . (isset($_SESSION['logged_in_user']) ? $_SESSION['logged_in_user'] : 'NOT SET') . "<br>";
echo "user_name: " . (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'NOT SET') . "<br>";
echo "is_super_admin: " . (isset($_SESSION['is_super_admin']) ? $_SESSION['is_super_admin'] : 'NOT SET') . "<br>";
echo "unit_id: " . (isset($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 'NOT SET') . "<br>";

// Check database connection
echo "<h3>Database Connection:</h3>";
try {
    $test_query = DB::query("SELECT 1 as test");
    echo "Database connection: OK<br>";
} catch (Exception $e) {
    echo "Database connection ERROR: " . $e->getMessage() . "<br>";
}

// Check if units table exists and has data
echo "<h3>Units Table Check:</h3>";
try {
    $count = DB::queryFirstField("SELECT COUNT(*) FROM units");
    echo "Units table record count: " . $count . "<br>";
    
    if ($count > 0) {
        echo "<h4>Sample Units:</h4>";
        $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name ASC LIMIT 5");
        foreach ($results as $row) {
            echo "ID: " . $row['unit_id'] . " - Name: " . $row['unit_name'] . "<br>";
        }
    }
} catch (Exception $e) {
    echo "Units table ERROR: " . $e->getMessage() . "<br>";
}

// Test the actual dropdown generation logic
echo "<h3>Dropdown Generation Test:</h3>";
if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == "Yes") {
    echo "Running super admin logic...<br>";
    try {
        $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name ASC");
        
        if (!empty($results)) {
            echo "Query returned " . count($results) . " results<br>";
            $output = "";
            foreach ($results as $row) {
                $output .= "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
            }
            
            echo "<h4>Generated Options HTML:</h4>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
            
            echo "<h4>Rendered Dropdown:</h4>";
            echo "<select>";
            echo "<option value=''>Select</option>";
            echo $output;
            echo "</select>";
        } else {
            echo "Query returned empty results<br>";
        }
    } catch (Exception $e) {
        echo "Error in dropdown generation: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Not super admin or session not set<br>";
    if (isset($_SESSION['unit_id'])) {
        echo "Running regular user logic for unit_id: " . $_SESSION['unit_id'] . "<br>";
        try {
            $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i", intval($_SESSION['unit_id']));
            echo "Found unit name: " . $unit_name . "<br>";
            echo "<select><option value='" . htmlspecialchars($_SESSION['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option></select>";
        } catch (Exception $e) {
            echo "Error in regular user logic: " . $e->getMessage() . "<br>";
        }
    }
}
?>