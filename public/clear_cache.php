<?php
// Clear opcache if enabled
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully<br>";
} else {
    echo "OPcache not available<br>";
}

// Clear any other caches
if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo "APC cache cleared<br>";
}

echo "Cache clearing completed. Please try accessing the page again.<br>";
echo "<a href='assignedcases.php'>Test assignedcases.php</a>";
?>