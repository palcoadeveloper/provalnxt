<?php
// Simple test page for visibility timeout functionality
require_once('./core/config/config.php');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Visibility Timeout Test</title>
    <script src="assets/js/jquery.min.js" type="text/javascript"></script>
</head>
<body>
    <h1>Session Security Test</h1>
    <p>This page will test the session security features:</p>
    <ul>
        <li><strong>Visibility Timeout:</strong> Switch to another application and wait for timeout</li>
        <li><strong>Screen Lock Detection:</strong> Lock your screen (mobile/tablet) or close lid (laptop) and wait for timeout</li>
    </ul>

    <div id="status">
        <p>Configuration:</p>
        <ul>
            <li>ENABLE_VISIBILITY_TIMEOUT: <?php echo ENABLE_VISIBILITY_TIMEOUT ? 'true' : 'false'; ?></li>
            <li>VISIBILITY_TIMEOUT: <?php echo VISIBILITY_TIMEOUT; ?> seconds</li>
            <li>ENABLE_SCREEN_LOCK_DETECTION: <?php echo ENABLE_SCREEN_LOCK_DETECTION ? 'true' : 'false'; ?></li>
            <li>SCREEN_LOCK_TIMEOUT: <?php echo SCREEN_LOCK_TIMEOUT; ?> seconds</li>
        </ul>
    </div>

    <div id="device-status">
        <p>Device Information:</p>
        <ul>
            <li>Device Type: <span id="device-type">Loading...</span></li>
            <li>Screen Width: <span id="screen-width">Loading...</span></li>
            <li>Touch Screen: <span id="touch-screen">Loading...</span></li>
            <li>Screen Lock Detection Active: <span id="screen-lock-active">Loading...</span></li>
        </ul>
    </div>

    <div id="debug-output">
        <h3>Debug Output:</h3>
        <div id="debug-log"></div>
    </div>

    <?php include('./assets/inc/_sessiontimeout.php'); ?>

    <script>
        // Add extra debug logging
        window.addEventListener('load', function() {
            console.log('Page loaded, checking for jQuery and SessionTimeoutManager...');
            console.log('jQuery available:', typeof $ !== 'undefined');

            if (window.sessionManager) {
                console.log('SessionTimeoutManager loaded successfully');
                console.log('Visibility timeout enabled:', window.sessionManager.enableVisibilityTimeout);
                console.log('Visibility timeout duration:', window.sessionManager.visibilityTimeout, 'ms');

                document.getElementById('debug-log').innerHTML += '<p style="color: green;"><strong>✅ SessionTimeoutManager loaded successfully!</strong></p>';
                document.getElementById('debug-log').innerHTML += '<p>Visibility timeout enabled: ' + window.sessionManager.enableVisibilityTimeout + '</p>';
                document.getElementById('debug-log').innerHTML += '<p>Visibility timeout duration: ' + (window.sessionManager.visibilityTimeout / 1000) + ' seconds</p>';

                // Populate device information
                document.getElementById('device-type').textContent = window.sessionManager.deviceType;
                document.getElementById('screen-width').textContent = window.screen.width + 'px';
                document.getElementById('touch-screen').textContent = ('ontouchstart' in window || navigator.maxTouchPoints > 0) ? 'Yes' : 'No';
                document.getElementById('screen-lock-active').textContent = window.sessionManager.enableScreenLockDetection ? 'Yes (Universal)' : 'No';

                // Override debug log to show on page
                const originalDebugLog = window.sessionManager.debugLog;
                window.sessionManager.debugLog = function(message, data, category) {
                    originalDebugLog.call(this, message, data, category);

                    const debugDiv = document.getElementById('debug-log');
                    const timestamp = new Date().toISOString();
                    debugDiv.innerHTML += `<p><strong>[${category || 'INFO'}] ${timestamp}:</strong> ${message}</p>`;
                    if (data) {
                        debugDiv.innerHTML += `<pre>${JSON.stringify(data, null, 2)}</pre>`;
                    }
                    debugDiv.scrollTop = debugDiv.scrollHeight;
                };
            } else {
                console.error('SessionTimeoutManager not loaded!');
                document.getElementById('debug-log').innerHTML = '<p style="color: red;"><strong>❌ ERROR: SessionTimeoutManager not loaded!</strong></p>';
                document.getElementById('debug-log').innerHTML += '<p>jQuery available: ' + (typeof $ !== 'undefined') + '</p>';
                document.getElementById('debug-log').innerHTML += '<p>Check browser console for more details.</p>';
            }
        });

        // Test visibility API
        document.addEventListener('visibilitychange', function() {
            console.log('Visibility changed:', document.hidden ? 'HIDDEN' : 'VISIBLE');
            const debugDiv = document.getElementById('debug-log');
            debugDiv.innerHTML += `<p><strong>[VISIBILITY]</strong> Page is now: ${document.hidden ? 'HIDDEN' : 'VISIBLE'}</p>`;
        });
    </script>
</body>
</html>