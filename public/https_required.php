<?php
require_once 'core/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Connection Required</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .error-container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 90%;
            text-align: center;
        }
        .error-icon {
            color: #dc3545;
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            color: #343a40;
            margin-bottom: 1rem;
        }
        p {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .secure-url {
            background-color: #e9ecef;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-family: monospace;
            margin: 1rem 0;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .security-info {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🔒</div>
        <h1>Secure Connection Required</h1>
        <p>This application requires a secure connection (HTTPS) to protect your data.</p>
        
        <div class="secure-url">
            <?php
            $secureUrl = str_replace('http://', 'https://', BASE_URL . $_SERVER['REQUEST_URI']);
            echo htmlspecialchars($secureUrl);
            ?>
        </div>
        
        <p>Please use the secure URL above to access the application.</p>
        
        <a href="<?php echo htmlspecialchars($secureUrl); ?>" class="btn">
            Continue to Secure Site
        </a>
        
        <div class="security-info">
            <p>For your security, this application enforces HTTPS to protect your data during transmission.</p>
            <p>If you continue to see this message, please contact your system administrator.</p>
        </div>
    </div>
</body>
</html> 