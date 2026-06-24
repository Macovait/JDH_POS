<?php
/**
 * Error Page - Friendly Error Handler
 * Displays user-friendly error messages
 */

$error_code = $_GET['code'] ?? '500';
$error_message = $_GET['message'] ?? 'An unexpected error occurred';

// Map error codes to messages
$error_types = [
    '400' => ['title' => 'Bad Request', 'description' => 'The request could not be understood.'],
    '401' => ['title' => 'Unauthorized', 'description' => 'You must be logged in to access this page.'],
    '403' => ['title' => 'Forbidden', 'description' => 'You do not have permission to access this resource.'],
    '404' => ['title' => 'Page Not Found', 'description' => 'The requested page could not be found.'],
    '500' => ['title' => 'Server Error', 'description' => 'An internal server error occurred.'],
    '503' => ['title' => 'Service Unavailable', 'description' => 'The service is temporarily unavailable.'],
];

$error_info = $error_types[$error_code] ?? $error_types['500'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($error_info['title']); ?> - JDH POS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0b1120;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            padding: 40px;
            max-width: 500px;
        }
        .error-code {
            font-size: 120px;
            font-weight: 900;
            color: #fbbf24;
            line-height: 1;
            margin-bottom: 20px;
            text-shadow: 0 0 40px rgba(251, 191, 36, 0.3);
        }
        .error-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #f8fafc;
        }
        .error-description {
            font-size: 16px;
            color: #94a3b8;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #fbbf24;
            color: #0f172a;
        }
        .btn-primary:hover {
            background: #f59e0b;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: transparent;
            color: #94a3b8;
            border: 1px solid #334155;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.05);
            color: #e2e8f0;
        }
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .error-details {
            margin-top: 32px;
            padding: 16px;
            background: rgba(30, 41, 59, 0.5);
            border-radius: 8px;
            border: 1px solid #334155;
            text-align: left;
        }
        .error-details summary {
            cursor: pointer;
            color: #64748b;
            font-size: 13px;
            list-style: none;
        }
        .error-details summary:hover {
            color: #94a3b8;
        }
        .error-trace {
            margin-top: 12px;
            padding: 12px;
            background: #0f172a;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #ef4444;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <?php if ($error_code == '404'): ?>🔍
            <?php elseif ($error_code == '403'): ?>🔒
            <?php elseif ($error_code == '401'): ?>🔐
            <?php else: ?>🚨
            <?php endif; ?>
        </div>
        
        <div class="error-code"><?php echo htmlspecialchars($error_code); ?></div>
        <h1 class="error-title"><?php echo htmlspecialchars($error_info['title']); ?></h1>
        <p class="error-description">
            <?php echo htmlspecialchars($error_info['description']); ?>
            <?php if (!empty($error_message) && $error_message !== $error_info['description']): ?>
                <br><br><?php echo htmlspecialchars($error_message); ?>
            <?php endif; ?>
        </p>
        
        <div class="error-actions">
            <a href="dashboard/home.php" class="btn btn-primary">
                <span>🏠</span> Back to Dashboard
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <span>⬅️</span> Go Back
            </a>
        </div>
        
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE && !empty($_SESSION['error_trace'])): ?>
        <details class="error-details">
            <summary>Debug Information</summary>
            <pre class="error-trace"><?php echo htmlspecialchars($_SESSION['error_trace']); ?></pre>
        </details>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
// Clear error trace from session
unset($_SESSION['error_trace']);
?>
