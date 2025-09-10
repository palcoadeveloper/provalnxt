<?php
// Simple test file to verify instruments functionality
require_once('./core/config/config.php');
require_once('core/config/db.class.php');

try {
    echo "<h2>Instrument System Test</h2>";
    
    // Test 1: Check if instruments table exists
    echo "<h3>Test 1: Table Structure</h3>";
    $result = DB::query("DESCRIBE instruments");
    if ($result) {
        echo "✅ Instruments table exists<br>";
        echo "<pre>";
        foreach ($result as $column) {
            echo $column['Field'] . " - " . $column['Type'] . "\n";
        }
        echo "</pre>";
    }
    
    // Test 2: Count records
    echo "<h3>Test 2: Record Count</h3>";
    $count = DB::queryFirstField("SELECT COUNT(*) FROM instruments");
    echo "📊 Total instruments: " . $count . "<br>";
    
    // Test 3: Test statistics queries
    echo "<h3>Test 3: Statistics</h3>";
    $active = DB::queryFirstField("SELECT COUNT(*) FROM instruments WHERE instrument_status = 'Active'");
    $expired = DB::queryFirstField("SELECT COUNT(*) FROM instruments WHERE calibration_due_on < CURDATE() AND instrument_status = 'Active'");
    $due_soon = DB::queryFirstField("SELECT COUNT(*) FROM instruments WHERE calibration_due_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND instrument_status = 'Active'");
    
    echo "🟢 Active instruments: " . $active . "<br>";
    echo "🔴 Expired instruments: " . $expired . "<br>";
    echo "🟡 Due soon instruments: " . $due_soon . "<br>";
    
    // Test 4: Sample data
    echo "<h3>Test 4: Sample Data</h3>";
    $samples = DB::query("SELECT instrument_id, instrument_type, instrument_status FROM instruments LIMIT 5");
    if ($samples) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Type</th><th>Status</th></tr>";
        foreach ($samples as $sample) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($sample['instrument_id']) . "</td>";
            echo "<td>" . htmlspecialchars($sample['instrument_type']) . "</td>";
            echo "<td>" . htmlspecialchars($sample['instrument_status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<br><strong>✅ All tests completed successfully!</strong><br>";
    echo "<a href='searchinstruments.php'>➡️ Go to Instruments Search</a><br>";
    echo "<a href='manageinstrumentdetails.php?m=a'>➕ Add New Instrument</a><br>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please ensure the database is set up correctly and run the SQL script first.</p>";
}
?>