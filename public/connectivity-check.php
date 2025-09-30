<?php
// Lightweight connectivity check endpoint
// Returns minimal response to verify server connectivity

// Set minimal headers for fastest response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Minimal response to confirm connectivity
$response = [
    'status' => 'online',
    'timestamp' => time()
];

// Return JSON response
echo json_encode($response);
exit();
?>