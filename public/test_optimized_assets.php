<?php
// Test file to verify optimized asset loading
define('ENVIRONMENT', 'prod'); // Test production mode
?>
<!DOCTYPE html>
<html>
<head>
    <?php include_once "assets/inc/_header.php"; ?>
</head>
<body>
    <div class="container">
        <h1>ProVal HVAC - Optimized Asset Test</h1>
        <p>This page tests the optimized asset loading with exactly 2 HTTP requests.</p>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Asset Optimization Status</h5>
                <p class="card-text">
                    <strong>Environment:</strong> <?= ENVIRONMENT ?><br>
                    <strong>Expected HTTP Requests:</strong> 2 (1 CSS + 1 JS)<br>
                    <strong>CSS Bundle:</strong> assets/dist/css/proval-styles.min.css<br>
                    <strong>JS Bundle:</strong> assets/dist/js/proval-combined.min.js
                </p>
            </div>
        </div>

        <div class="alert alert-success mt-3">
            ✅ If you see this styled properly, the CSS optimization is working!
        </div>

        <button class="btn btn-primary" onclick="testJS()">Test JavaScript</button>
        <div id="js-test-result" class="mt-2"></div>
    </div>

    <script>
    function testJS() {
        // Test if jQuery and other libraries are loaded
        if (typeof $ !== 'undefined') {
            $('#js-test-result').html('<div class="alert alert-success">✅ JavaScript optimization working! jQuery and other libraries loaded successfully.</div>');
        } else {
            $('#js-test-result').html('<div class="alert alert-danger">❌ JavaScript optimization failed - jQuery not loaded.</div>');
        }
    }
    </script>

    <?php include "assets/inc/_footerjs.php"; ?>
</body>
</html>